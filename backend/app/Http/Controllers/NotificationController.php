<?php

namespace App\Http\Controllers;
use App\Events\NotificationSent;
use App\Models\Notification;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;


class NotificationController extends Controller
{

    public function sendNotification(Request $request)
    {
        // Validate and process the notification data from the request
        // Save the notification to the database

        $request->validate([
            'type' => 'required|string',
            'send_to' => 'required|numeric|exists:users,id',
            'note' => 'required|string',
            'content.*.id' => 'required|numeric'
        ]);

        $data = $request->content;
        foreach($data as $elm){
            if ($request->type == 'مراجعة' && auth()->user()->department == 'Admin') {
                if ($elm['review_notifications_count'] > 0) {
                    Notification::where('type' , 'مراجعة')->where('order_id' , $elm['id'])->delete();
                }
            }
            Notification::create([
                'send_from' =>  auth()->id(),
                'send_to' => $request->send_to,
                'type' => $request->type,
                'ref' => $elm['id'],
                'order_id' => $elm['id'],
                'note' => $request->note,
            ]);
        }
        Cache::forget('user_notifications_' . $request->send_to);
        // Broadcast the notification to the specified user using Laravel Echo
        // broadcast(new NotificationSent('test'));

        return response()->json($data,200);
    }

    public function getById()
    {

        $notifications = Notification::where('send_to', auth()->id())
                ->with('sender')->orderBy('id', 'desc')->limit(200)->get();


        $confirmedOrders = collect();
            if (auth()->user()->department == 'Admin' || auth()->user()->department == 'Shipping Management' || auth()->user()->department == 'Operation Management') {
                $confirmedOrders = Order::where('order_status', 'طلب مؤكد')
                    ->whereHas('order_details', function ($query) {
                        $query->where('status_date', '<', Carbon::now()->subDays(3));
                    })
                    ->get();
            }

        $response = [
            'notifications' => $notifications,
            'confirmedOrders' => $confirmedOrders,
            'confirmedOrdersCount' => $confirmedOrders->count(),
        ];

        return response()->json($response, 200);
    }

    public function readNotify($id)
    {
        $notifications = Notification::find($id);
        if ($notifications->is_read == 0) {
            $notifications->is_read = 1;
            $notifications->save();
        }
        Cache::forget('user_notifications_' . auth()->user()->id);
        return response()->json($notifications, 200);
    }

    public function readOrderNotify($id , Request $request)
    {

        $orders =  $request->orders;
        foreach ($orders as $elm) {
            $notifications = Notification::find($elm['id']);
            if ($notifications->is_read == 0) {
                $notifications->is_read = 1;
                $notifications->save();
            }
        }
        Cache::forget('user_notifications_' . auth()->user()->id);
        return response()->json('success', 200);
    }


    public function recievedNotifiy(Request $request){

        $itemsPerPage = request('itemsPerPage') ? request('itemsPerPage') : 10;
        $search = Notification::query()->where('send_to', auth()->id())->with('sender');
        if($request->has('type')){
            $search->where('type',$request->type);
        }
        if($request->has('send_from')){
            $search->where('send_from',$request->send_from);
        }
        if($request->has('is_read')){
            $search->where('is_read',$request->is_read);
        }
        if($request->has('order_id')){
            $search->where('order_id','like' , '%'.$request->order_id.'%');
        }
        if($request->has('review_status_user')){
            $search->where('review_status',$request->review_status_user);
            $search->whereIn('type',['مراجعة','مراجعة مؤقتة']);
        }

        $search = $search->orderBy('id' , 'desc')->paginate($itemsPerPage);
        return response()->json($search, 200);
    }


    public function sentNotifiy(Request $request){

        $itemsPerPage = request('itemsPerPage') ? request('itemsPerPage') : 10;
        $search = Notification::query()->where('send_from', auth()->id())->with('receiver');
        if($request->has('type')){
            $search->where('type',$request->type);
        }
        if($request->has('order_id')){
            $search->where('order_id','like' , '%'.$request->order_id.'%');
        }
        if($request->has('send_to')){
            $search->where('send_to',$request->send_to);
        }
        if($request->has('is_read')){
            $search->where('is_read',$request->is_read);
        }

        if($request->has('review_status_admin')){
            $search->where('review_status',$request->review_status_admin);
            // $search->where('type','مراجعة');
            $search->whereIn('type',['مراجعة','مراجعة مؤقتة']);
        }

        if($request->has('review_status_user')){
            $search->where('review_status',$request->review_status_user);
            // $search->where('type','مراجعة');
            $search->whereIn('type',['مراجعة','مراجعة مؤقتة']);
        }

        $search = $search->orderBy('id' , 'desc')->paginate($itemsPerPage);
        return response()->json($search, 200);
    }

    public function allNotifiy(Request $request){

        $itemsPerPage = request('itemsPerPage') ? request('itemsPerPage') : 10;
        $search = Notification::query()->with(['receiver','sender']);
        if($request->has('type')){
            $search->where('type',$request->type);
        }
        if($request->has('send_to')){
            $search->where('send_to',$request->send_to);
        }
        if($request->has('order_id')){
            $search->where('order_id','like' , '%'.$request->order_id.'%');
        }
        if($request->has('send_from')){
            $search->where('send_from',$request->send_from);
        }
        if($request->has('is_read')){
            $search->where('is_read',$request->is_read);
        }

        if($request->has('review_status_admin')){
            $search->where('review_status',$request->review_status_admin);
            $search->where('type','مراجعة');
        }

        if($request->has('review_status_user')){
            $search->where('review_status',$request->review_status_user);
            $search->whereIn('type',['مراجعة','مراجعة مؤقتة']);
        }

        $search = $search->orderBy('id' , 'desc')->paginate($itemsPerPage);
        return response()->json($search, 200);
    }

    public function destroy($id)
    {
        $user = Notification::find($id);
        if(!$user){
        return response()->json(['error' => 'Not Found'], 404);
        }
        $user->delete();
        Cache::forget('user_notifications_' . $user->send_to);
        Cache::forget('user_notifications_' . $user->send_from);
        return response()->json('deleted sucuessfully');
    }




}
