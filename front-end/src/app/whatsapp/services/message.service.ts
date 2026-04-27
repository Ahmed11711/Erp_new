import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable, of, shareReplay } from 'rxjs';
import { catchError, map, tap } from 'rxjs/operators';
import { environment } from 'src/env/env';

export type MessageDirection = 'sent' | 'received';
export type MessageType = 'text' | 'image' | 'video' | 'audio' | 'document' | 'sticker' | string;

export interface ChatMessage {
  id: number;
  message: string;
  type: MessageType;
  direction: MessageDirection;
  status?: string;
  media_url: string | null;
  media_mime_type?: string | null;
  media_filename?: string | null;
  media_caption?: string | null;
  sender?: { id: number; name: string } | null;
  created_at: string;
}

export interface MessagesPage {
  success: boolean;
  conversation?: { id: number; name: string; phone: string };
  data: ChatMessage[];
  next_cursor: string | null;
  has_more: boolean;
  limit?: number;
}

export interface MessagesFilters {
  cursor?: string | null;
  limit?: number;
  search?: string;
  from_date?: string;
  to_date?: string;
}

/**
 * API client for the WhatsApp-Web style chat:
 *  - Cursor-based message pagination (never returns everything at once).
 *  - Media proxy with in-memory blob-URL cache (so images render in <img>
 *    tags without exposing the Meta access token).
 */
@Injectable({ providedIn: 'root' })
export class MessageService {
  private readonly baseUrl = environment.Url;
  private readonly mediaCache = new Map<number, Observable<string>>();
  private readonly resolvedBlobUrls = new Map<number, string>();

  constructor(private http: HttpClient) {}

  getMessages(conversationId: number, filters: MessagesFilters = {}): Observable<MessagesPage> {
    let params = new HttpParams();

    if (filters.cursor) {
      params = params.set('cursor', filters.cursor);
    }
    params = params.set('limit', String(filters.limit ?? 20));
    if (filters.search && filters.search.trim().length) {
      params = params.set('search', filters.search.trim());
    }
    if (filters.from_date) {
      params = params.set('from_date', filters.from_date);
    }
    if (filters.to_date) {
      params = params.set('to_date', filters.to_date);
    }

    return this.http.get<MessagesPage>(
      `${this.baseUrl}/conversations/${conversationId}/messages`,
      { params }
    );
  }

  /** Raw blob for inline preview (image/video tags). Cached per messageId. */
  getMediaObjectUrl(messageId: number): Observable<string> {
    const cached = this.mediaCache.get(messageId);
    if (cached) {
      return cached;
    }

    const stream$ = this.http
      .get(`${this.baseUrl}/media/${messageId}`, { responseType: 'blob' })
      .pipe(
        map((blob) => URL.createObjectURL(blob)),
        tap((url) => {
          if (url) this.resolvedBlobUrls.set(messageId, url);
        }),
        catchError(() => {
          this.mediaCache.delete(messageId);
          return of('');
        }),
        shareReplay(1)
      );

    this.mediaCache.set(messageId, stream$);
    return stream$;
  }

  /** Triggers a browser download. Works across auth schemes because the blob is
   *  fetched through HttpClient (which already carries the auth headers). */
  downloadMedia(messageId: number, suggestedName?: string): Observable<boolean> {
    return this.http
      .get(`${this.baseUrl}/media/${messageId}`, {
        responseType: 'blob',
        params: { download: '1' },
      })
      .pipe(
        map((blob) => {
          const url = URL.createObjectURL(blob);
          const a = document.createElement('a');
          a.href = url;
          a.download = suggestedName || `whatsapp-media-${messageId}`;
          document.body.appendChild(a);
          a.click();
          document.body.removeChild(a);
          setTimeout(() => URL.revokeObjectURL(url), 2000);
          return true;
        }),
        catchError(() => of(false))
      );
  }

  /** Call on conversation switch / component destroy to release blob URLs. */
  releaseCache(): void {
    this.resolvedBlobUrls.forEach((url) => {
      try {
        URL.revokeObjectURL(url);
      } catch {
        /* noop */
      }
    });
    this.resolvedBlobUrls.clear();
    this.mediaCache.clear();
  }
}
