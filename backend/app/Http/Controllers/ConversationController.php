<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Message;
use App\Models\Order;
use App\Services\MetaWhatsAppService;
use App\Services\Orders\OrderStatusVisibilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * WhatsApp-Web style chat API:
 *  - Cursor-paginated messages (newest first, "load more" = older).
 *  - Text / contact name search + date range filter.
 *  - Media proxy that streams bytes from Meta through Laravel (with local cache)
 *    so <img> / download links work without exposing the Meta access token.
 *
 * Conventions:
 *  - `conversation_id` in the URL == `customers.id` (each customer = one conversation).
 *  - `cursor` is the **id of the oldest message currently loaded**; the endpoint
 *    returns the next batch with `id < cursor`. This keeps pagination O(1) when
 *    paired with the `(customer_id, id)` composite index.
 */
class ConversationController extends Controller
{
    private const DEFAULT_LIMIT = 20;
    private const MAX_LIMIT = 100;
    private const MEDIA_CACHE_DIR = 'whatsapp_media';
    private const MEDIA_CACHE_TTL = 60 * 60 * 24 * 7; // 7 days
    private const ORDERS_LIMIT = 25;

    public function __construct(
        private OrderStatusVisibilityService $orderStatusVisibility
    ) {}

    /**
     * GET /api/conversations/{conversation_id}/messages
     *
     * Query params:
     *  - cursor (int, optional)  : last-seen message id; returns messages older than it
     *  - limit  (int, optional)  : default 20, max 100
     *  - search (string, optional): matches message content OR customer name
     *  - from_date (YYYY-MM-DD)  : inclusive lower bound on created_at
     *  - to_date   (YYYY-MM-DD)  : inclusive upper bound on created_at
     */
    public function messages(Request $request, int $conversationId): JsonResponse
    {
        $customer = Customer::find($conversationId);
        if (! $customer) {
            return response()->json([
                'success' => false,
                'error' => 'Conversation not found',
            ], 404);
        }

        $customer->restoreIfHasNewerInboundReply();
        $customer->refresh();

        $limit = (int) $request->query('limit', self::DEFAULT_LIMIT);
        $limit = max(1, min(self::MAX_LIMIT, $limit));
        $cursor = $request->query('cursor');
        $search = trim((string) $request->query('search', ''));
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');

        $query = Message::query()
            ->where('customer_id', $conversationId)
            ->with(['sender:id,name']);

        if ($cursor !== null && $cursor !== '' && ctype_digit((string) $cursor)) {
            $query->where('id', '<', (int) $cursor);
        }

        if ($search !== '') {
            // customer name filter first (single row) to avoid joining customers in every row scan
            $nameMatches = stripos((string) $customer->name, $search) !== false;

            $query->where(function ($q) use ($search, $nameMatches) {
                $q->where('content', 'like', '%' . $search . '%')
                    ->orWhere('media_caption', 'like', '%' . $search . '%');

                // If the contact name itself matches, include every message in the conversation.
                if ($nameMatches) {
                    $q->orWhereRaw('1 = 1');
                }
            });
        }

        if ($fromDate) {
            try {
                $query->where('created_at', '>=', Carbon::parse($fromDate)->startOfDay());
            } catch (\Throwable $e) { /* ignore invalid date */ }
        }
        if ($toDate) {
            try {
                $query->where('created_at', '<=', Carbon::parse($toDate)->endOfDay());
            } catch (\Throwable $e) { /* ignore invalid date */ }
        }

        // Fetch limit + 1 to detect hasMore cheaply.
        $rows = $query->orderByDesc('id')
            ->limit($limit + 1)
            ->get();

        $hasMore = $rows->count() > $limit;
        if ($hasMore) {
            $rows = $rows->slice(0, $limit)->values();
        }

        // Caller UX is "newest at bottom". Return batch in ASC order so the
        // frontend can simply append/prepend without re-sorting.
        $ordered = $rows->sortBy('id')->values();

        $nextCursor = $hasMore ? (string) $rows->min('id') : null;

        return response()->json([
            'success' => true,
            'conversation' => $this->formatConversation($customer),
            'data' => $ordered->map(fn (Message $m) => $this->formatMessage($m))->all(),
            'next_cursor' => $nextCursor,
            'has_more' => $hasMore,
            'limit' => $limit,
        ]);
    }

    /**
     * GET /api/conversations/{conversation_id}/orders
     *
     * Orders linked to this WhatsApp contact (message.order_id and matching phone),
     * including product lines so the chat can open a request without leaving the thread.
     */
    public function orders(Request $request, int $conversationId): JsonResponse
    {
        $customer = Customer::find($conversationId);
        if (! $customer) {
            return response()->json([
                'success' => false,
                'error' => 'Conversation not found',
            ], 404);
        }

        $user = $request->user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthenticated',
            ], 401);
        }

        $messageOrderIds = Message::query()
            ->where('customer_id', $conversationId)
            ->whereNotNull('order_id')
            ->distinct()
            ->pluck('order_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values();

        $includeId = (int) $request->query('include_id', 0);
        if ($includeId > 0 && ! $messageOrderIds->contains($includeId)) {
            $messageOrderIds->push($includeId);
        }

        $phoneVariants = $this->phoneDigitsVariants((string) $customer->phone);

        $query = Order::query()->with(['order_products.category:id,category_name']);

        $query->where(function ($q) use ($phoneVariants, $messageOrderIds) {
            $hasClause = false;
            if ($phoneVariants !== []) {
                $hasClause = true;
                $q->where(function ($phoneQ) use ($phoneVariants) {
                    foreach ($phoneVariants as $variant) {
                        $phoneQ->orWhere('customer_phone_1', 'like', '%'.$variant.'%')
                            ->orWhere('customer_phone_2', 'like', '%'.$variant.'%');
                    }
                });
            }
            if ($messageOrderIds->isNotEmpty()) {
                if ($hasClause) {
                    $q->orWhereIn('id', $messageOrderIds->all());
                } else {
                    $q->whereIn('id', $messageOrderIds->all());
                }
            } elseif (! $hasClause) {
                $q->whereRaw('0 = 1');
            }
        });

        $this->orderStatusVisibility->applySearchScope($query, $user);

        $orders = $query->orderByDesc('id')
            ->limit(self::ORDERS_LIMIT)
            ->get();

        return response()->json([
            'success' => true,
            'conversation' => $this->formatConversation($customer),
            'data' => $orders->map(fn (Order $order) => $this->formatConversationOrder($order))->all(),
        ]);
    }

    /**
     * GET /api/media/{id}
     *
     * Streams media bytes for a message row id. The request is authenticated
     * through the normal API middleware, so there is no way for an anonymous
     * caller to enumerate media. Meta's CDN URLs expire every 5 minutes, which
     * is why we proxy (and cache) the bytes locally.
     */
    public function media(Request $request, int $id)
    {
        $message = Message::find($id);
        if (! $message || ! $message->isMedia()) {
            return response()->json(['error' => 'Media not found'], 404);
        }

        $download = filter_var($request->query('download', false), FILTER_VALIDATE_BOOLEAN);
        $cachePath = $this->cachePathFor($message);

        // Serve from local cache when available.
        if (Storage::disk('local')->exists($cachePath)) {
            $absolute = Storage::disk('local')->path($cachePath);
            return $this->fileResponse(
                $absolute,
                $message->media_mime_type ?: 'application/octet-stream',
                $download,
                $this->downloadFilename($message)
            );
        }

        // Otherwise fetch from Meta, cache, and stream.
        $service = new MetaWhatsAppService($message->phone_number_id);
        if (! $service->isConfigured()) {
            return response()->json(['error' => 'Meta WhatsApp not configured'], 502);
        }

        $result = $service->downloadMediaBytes((string) $message->media_id);
        if (! $result['success']) {
            Log::warning('Media proxy: download failed', [
                'message_id' => $message->id,
                'media_id' => $message->media_id,
                'error' => $result['error'] ?? null,
            ]);
            return response()->json(['error' => 'Unable to fetch media'], 502);
        }

        $mime = $result['mime_type'] ?: ($message->media_mime_type ?: 'application/octet-stream');

        try {
            Storage::disk('local')->put($cachePath, $result['body']);
            if (! $message->media_mime_type && $mime) {
                $message->media_mime_type = $mime;
                $message->save();
            }
        } catch (\Throwable $e) {
            Log::warning('Media proxy: cache write failed', ['e' => $e->getMessage()]);
        }

        return $this->streamBytes($result['body'], $mime, $download, $this->downloadFilename($message));
    }

    /**
     * @return array{id: int, name: string|null, phone: string|null, is_archived: bool, whatsapp_archived_at: string|null, awaiting_reply: bool}
     */
    private function formatConversation(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'is_archived' => $customer->isWhatsappArchived(),
            'whatsapp_archived_at' => optional($customer->whatsapp_archived_at)->format('Y-m-d H:i:s'),
            'awaiting_reply' => $customer->isAwaitingWhatsappReply(),
        ];
    }

    private function formatMessage(Message $m): array
    {
        $isMedia = $m->isMedia();

        return [
            'id' => $m->id,
            'message' => $m->content,
            'type' => $m->type ?: 'text',
            'direction' => $m->direction === 'outbound' ? 'sent' : 'received',
            'status' => $m->status,
            'order_id' => $this->resolveMessageOrderId($m),
            'media_url' => $isMedia ? url('api/media/' . $m->id) : null,
            'media_mime_type' => $m->media_mime_type,
            'media_filename' => $m->media_filename,
            'media_caption' => $m->media_caption,
            'sender' => $m->sender ? [
                'id' => $m->sender->id,
                'name' => $m->sender->name,
            ] : null,
            'created_at' => optional($m->created_at)->format('Y-m-d H:i:s'),
        ];
    }

    private function resolveMessageOrderId(Message $m): ?int
    {
        if ($m->order_id) {
            return (int) $m->order_id;
        }

        $content = (string) $m->content;
        if ($content !== '' && preg_match('/(?:Order|طلب)\s*#?\s*(\d{3,})/iu', $content, $match)) {
            return (int) $match[1];
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function phoneDigitsVariants(string $phone): array
    {
        $digits = preg_replace('/\D/', '', $phone) ?: '';
        if ($digits === '') {
            return [];
        }

        $variants = [$digits];
        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $variants[] = '20'.substr($digits, 1);
            $variants[] = substr($digits, 1);
        } elseif (strlen($digits) === 12 && str_starts_with($digits, '20')) {
            $variants[] = '0'.substr($digits, 2);
            $variants[] = substr($digits, 2);
        } elseif (strlen($digits) === 10 && str_starts_with($digits, '1')) {
            $variants[] = '0'.$digits;
            $variants[] = '20'.$digits;
        }

        if (strlen($digits) >= 10) {
            $variants[] = substr($digits, -10);
        }

        return array_values(array_unique(array_filter(
            $variants,
            static fn ($v) => $v !== '' && strlen($v) >= 8
        )));
    }

    private function formatConversationOrder(Order $order): array
    {
        $products = $order->order_products->map(function ($product) {
            $qty = (float) ($product->quantity ?? 0);
            $price = (float) ($product->price ?? 0);
            $total = $product->total_price !== null
                ? (float) $product->total_price
                : round($qty * $price, 2);

            return [
                'id' => $product->id,
                'name' => $product->category?->category_name ?: 'صنف',
                'quantity' => $qty,
                'price' => $price,
                'total' => $total,
                'special_details' => $product->special_details,
            ];
        })->values()->all();

        return [
            'id' => $order->id,
            'customer_name' => $order->customer_name,
            'order_status' => $order->order_status,
            'order_type' => $order->order_type,
            'order_date' => $order->order_date,
            'net_total' => (float) ($order->net_total ?? 0),
            'prepaid_amount' => (float) ($order->prepaid_amount ?? 0),
            'products' => $products,
        ];
    }

    private function cachePathFor(Message $m): string
    {
        $ext = $this->extensionFor($m);
        return self::MEDIA_CACHE_DIR . '/' . $m->id . ($ext ? '.' . $ext : '');
    }

    private function extensionFor(Message $m): ?string
    {
        if ($m->media_filename && str_contains($m->media_filename, '.')) {
            return strtolower(pathinfo($m->media_filename, PATHINFO_EXTENSION));
        }
        return match (strtolower((string) $m->media_mime_type)) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'video/mp4' => 'mp4',
            'video/3gpp' => '3gp',
            'audio/ogg' => 'ogg',
            'audio/mpeg' => 'mp3',
            'audio/aac' => 'aac',
            'audio/amr' => 'amr',
            'application/pdf' => 'pdf',
            default => null,
        };
    }

    private function downloadFilename(Message $m): string
    {
        if ($m->media_filename) {
            return $m->media_filename;
        }
        $ext = $this->extensionFor($m);
        return 'whatsapp-media-' . $m->id . ($ext ? '.' . $ext : '');
    }

    private function fileResponse(string $absolutePath, string $mime, bool $download, string $filename): StreamedResponse
    {
        $disposition = $download ? 'attachment' : 'inline';

        return response()->stream(function () use ($absolutePath) {
            $stream = fopen($absolutePath, 'rb');
            if ($stream === false) {
                return;
            }
            while (! feof($stream)) {
                echo fread($stream, 8192);
                @ob_flush();
                @flush();
            }
            fclose($stream);
        }, 200, [
            'Content-Type' => $mime,
            'Content-Length' => (string) File::size($absolutePath),
            'Cache-Control' => 'private, max-age=' . self::MEDIA_CACHE_TTL,
            'Content-Disposition' => $disposition . '; filename="' . addslashes($filename) . '"',
        ]);
    }

    private function streamBytes(string $bytes, string $mime, bool $download, string $filename): Response
    {
        $disposition = $download ? 'attachment' : 'inline';

        return response($bytes, 200, [
            'Content-Type' => $mime,
            'Content-Length' => (string) strlen($bytes),
            'Cache-Control' => 'private, max-age=' . self::MEDIA_CACHE_TTL,
            'Content-Disposition' => $disposition . '; filename="' . addslashes($filename) . '"',
        ]);
    }
}
