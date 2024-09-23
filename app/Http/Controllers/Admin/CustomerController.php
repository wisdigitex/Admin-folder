<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Models\Order;
use App\Models\Newsletter;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\Models\BusinessSetting;
use Illuminate\Support\Facades\DB;
use App\Exports\CustomerListExport;
use App\Exports\CustomerOrderExport;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;
use Rap2hpoutre\FastExcel\FastExcel;
use App\Exports\SubscriberListExport;

class CustomerController extends Controller
{
    public function __construct()
    {
        DB::statement("SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''));");
    }
    public function customer_list(Request $request)
    {
        $zone_id=  $request->zone_id ?? null;
        $filter=  $request->filter ?? null;
        $order_wise=  $request->order_wise ?? null;
        $key = [];
        if ($request->search) {
            $key = explode(' ', $request['search']);
        }
        $customers = User::when(count($key) > 0, function ($query) use ($key) {
            foreach ($key as $value) {
                $query->orWhere('f_name', 'like', "%{$value}%")
                    ->orWhere('l_name', 'like', "%{$value}%")
                    ->orWhere('email', 'like', "%{$value}%")
                    ->orWhere('phone', 'like', "%{$value}%");
            };
        })->withcount('orders')

        ->when(isset($zone_id) && is_numeric($zone_id) , function ($query) use($zone_id){
            $query->where('zone_id' ,$zone_id);
        })
        ->when(isset($filter) && $filter == 'active' , function ($query) {
            $query->where('status' ,1);
        })
        ->when(isset($filter) && $filter == 'blocked' , function ($query) {
            $query->where('status' ,0);
        })
        ->when(isset($filter) && $filter == 'new' , function ($query) {
            $query->whereDate('created_at', '>=', now()->subDays(30)->format('Y-m-d'));
        })
        ->when(isset($order_wise) && $order_wise == 'top' , function ($query) {
            $query->orderBy('orders_count', 'desc');
        })
        ->when(isset($order_wise) && $order_wise == 'least' , function ($query) {
            $query->orderBy('orders_count', 'asc');
        })
        ->when(isset($order_wise) && $order_wise == 'latest' , function ($query) {
            $query->latest();
        })
        ->when(!$order_wise, function ($query) {
            $query->orderBy('orders_count', 'desc');
        })
            ->paginate(config('default_pagination'));

        return view('admin-views.customer.list', compact('customers'));
    }

    public function status(User $customer, Request $request)
    {
        $customer->status = $request->status;
        $customer->save();

        try {
            if ($request->status == 0) {
                $customer->tokens->each(function ($token, $key) {
                    $token->delete();
                });
                if (isset($customer->cm_firebase_token) && Helpers::getNotificationStatusData('customer','customer_account_block','push_notification_status') ) {
                    $data = [
                        'title' => translate('messages.suspended'),
                        'description' => translate('messages.your_account_has_been_blocked'),
                        'order_id' => '',
                        'image' => '',
                        'type' => 'block'
                    ];
                    Helpers::send_push_notif_to_device($customer->cm_firebase_token, $data);

                    DB::table('user_notifications')->insert([
                        'data' => json_encode($data),
                        'user_id' => $customer->id,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }

                if ( config('mail.status') && Helpers::get_mail_status('suspend_mail_status_user') == '1' &&  Helpers::getNotificationStatusData('customer','customer_account_block','mail_status') )  {
                    Mail::to($customer->email)->send(new \App\Mail\UserStatus('suspended', $customer->f_name.' '.$customer->l_name));
                }

            } else{

                if(Helpers::getNotificationStatusData('customer','customer_account_unblock','push_notification_status')  && isset($customer->cm_firebase_token))
                {
                    $data = [
                        'title' => translate('messages.account_activation'),
                        'description' => translate('messages.your_account_has_been_activated'),
                        'order_id' => '',
                        'image' => '',
                        'type'=> 'unblock'
                    ];
                    Helpers::send_push_notif_to_device($customer->cm_firebase_token, $data);

                    DB::table('user_notifications')->insert([
                        'data'=> json_encode($data),
                        'user_id'=>$customer->id,
                        'created_at'=>now(),
                        'updated_at'=>now()
                    ]);
                }

                if ( config('mail.status') && Helpers::get_mail_status('unsuspend_mail_status_user')== '1' &&  Helpers::getNotificationStatusData('customer','customer_account_unblock','mail_status') ) {
                    Mail::to($customer->email)->send(new \App\Mail\UserStatus('unsuspended', $customer->f_name.' '.$customer->l_name));
                }
            }


        } catch (\Exception $e) {
            Toastr::warning(translate('messages.push_notification_faild'));
        }

        Toastr::success(translate('messages.customer') . translate('messages.status_updated'));
        return back();
    }

    public function search(Request $request)
    {
        $key = explode(' ', $request['search']);
        $customers = User::where(function ($q) use ($key) {
            foreach ($key as $value) {
                $q->orWhere('f_name', 'like', "%{$value}%")
                    ->orWhere('l_name', 'like', "%{$value}%")
                    ->orWhere('email', 'like', "%{$value}%")
                    ->orWhere('phone', 'like', "%{$value}%");
            }
        })->orderBy('order_count', 'desc')->limit(50)->get();
        return response()->json([
            'count' => count($customers),
            'view' => view('admin-views.customer.partials._table', compact('customers'))->render()
        ]);
    }

    public function view(Request $request,$id)
    {
        $key = $request['search'];
        $customer = User::find($id);
        if (isset($customer)) {
            $total_order_amount = Order::selectRaw('sum(order_amount) as total_order_amount')->latest()->where(['user_id' => $id])
                ->when(isset($key), function($query) use($key){
                    $query->Where('id', 'like', "%{$key}%");
                } )
                ->Notpos()->get();
            $orders = Order::withcount('details')->latest()->where(['user_id' => $id])
            ->when(isset($key), function($query) use($key){
                $query->Where('id', 'like', "%{$key}%");
            } )
            ->Notpos()->paginate(config('default_pagination'));
            return view('admin-views.customer.customer-view', compact('customer', 'orders','total_order_amount'));
        }
        Toastr::error(translate('messages.customer_not_found'));
        return back();
    }

    public function customer_order_export(Request $request)
    {
        $customer = User::find($request->id);

        $orders = Order::latest()->where(['user_id' => $request->id])->Notpos()->get();

        $data = [
            'orders'=>$orders,
            'customer_id'=>$customer->id,
            'customer_name'=>$customer->f_name.' '.$customer->l_name,
            'customer_phone'=>$customer->phone,
            'customer_email'=>$customer->email,
        ];

        if ($request->type == 'excel') {
            return Excel::download(new CustomerOrderExport($data), 'CustomerOrders.xlsx');
        } else if ($request->type == 'csv') {
            return Excel::download(new CustomerOrderExport($data), 'CustomerOrders.csv');
        }
    }

    public function subscribedCustomers(Request $request)
    {
        $key = explode(' ', $request['search']);
        $data['subscribedCustomers'] = Newsletter::orderBy('id', 'desc')

        ->when(isset($key), function($query) use($key) {
            $query->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('email', 'like', "%". $value."%");
                }
            });
        })
        ->paginate(config('default_pagination'));
        return view('admin-views.customer.subscribed-emails', $data);
    }

    public function subscribed_customer_export(Request $request){
        $key = explode(' ', $request['search']);
        $customers = Newsletter::orderBy('id', 'desc')

        ->when(isset($key), function($query) use($key) {
            $query->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('email', 'like', "%". $value."%");
                }
            });
        })
        ->get();
        $data = [
            'customers'=>$customers
        ];

        if ($request->type == 'excel') {
            return Excel::download(new SubscriberListExport($data), 'Subscribers.xlsx');
        } else if ($request->type == 'csv') {
            return Excel::download(new SubscriberListExport($data), 'Subscribers.csv');
        }
    }

    public function subscriberMailSearch(Request $request)
    {
        $key = explode(' ', $request['search']);
        $customers = Newsletter::
        where(function ($q) use ($key) {
            foreach ($key as $value) {
                $q->orWhere('email', 'like', "%". $value."%");
            }
        })

        ->orderBy('id', 'desc')->get();
        return response()->json([
            'count' => count($customers),
            'view' => view('admin-views.customer.partials._subscriber-email-table', compact('customers'))->render()
        ]);
    }

    public function get_customers(Request $request){
        $key = explode(' ', $request['q']);
        $data = User::
        where(function ($q) use ($key) {
            foreach ($key as $value) {
                $q->orWhere('f_name', 'like', "%{$value}%")
                ->orWhere('l_name', 'like', "%{$value}%")
                ->orWhere('phone', 'like', "%{$value}%");
            }
        })
        ->limit(8)
        ->get([DB::raw('id, CONCAT(f_name, " ", l_name, " (", phone ,")") as text')]);
        if($request->all) $data[]=(object)['id'=>false, 'text'=>translate('messages.all')];


        return response()->json($data);
    }

    public function settings()
    {
        $data = BusinessSetting::where('key','like','wallet_%')
            ->orWhere('key','like','loyalty_%')
            ->orWhere('key','like','ref_earning_%')
            ->orWhere('key','like','ref_earning_%')->get();
        $data = array_column($data->toArray(), 'value','key');
        // dd($data);
        return view('admin-views.customer.settings', compact('data'));
    }

    public function update_settings(Request $request)
    {
        if (env('APP_MODE') == 'demo') {
            Toastr::info(translate('messages.update_option_is_disable_for_demo'));
            return back();
        }

        $request->validate([
            'add_fund_bonus'=>'nullable|numeric|max:100|min:0',
            'loyalty_point_exchange_rate'=>'nullable|numeric',
            'ref_earning_exchange_rate'=>'nullable|numeric',
        ]);
        BusinessSetting::updateOrInsert(['key' => 'customer_verification'], [
            'value' => $request['customer_verification_status']??0
        ]);
        BusinessSetting::updateOrInsert(['key' => 'wallet_status'], [
            'value' => $request['customer_wallet']??0
        ]);
        BusinessSetting::updateOrInsert(['key' => 'loyalty_point_status'], [
            'value' => $request['customer_loyalty_point']??0
        ]);
        BusinessSetting::updateOrInsert(['key' => 'ref_earning_status'], [
            'value' => $request['ref_earning_status'] ?? 0
        ]);
        BusinessSetting::updateOrInsert(['key' => 'wallet_add_refund'], [
            'value' => $request['refund_to_wallet']??0
        ]);
        BusinessSetting::updateOrInsert(['key' => 'loyalty_point_exchange_rate'], [
            'value' => $request['loyalty_point_exchange_rate'] ?? 0
        ]);
        BusinessSetting::updateOrInsert(['key' => 'ref_earning_exchange_rate'], [
            'value' => $request['ref_earning_exchange_rate'] ?? 0
        ]);
        BusinessSetting::updateOrInsert(['key' => 'loyalty_point_item_purchase_point'], [
            'value' => $request['item_purchase_point']??0
        ]);
        BusinessSetting::updateOrInsert(['key' => 'loyalty_point_minimum_point'], [
            'value' => $request['minimun_transfer_point']??0
        ]);
        BusinessSetting::updateOrInsert(['key' => 'add_fund_status'], [
            'value' => $request['add_fund_status']??0
        ]);

        BusinessSetting::updateOrInsert(['key' => 'new_customer_discount_status'], [
            'value' => $request['new_customer_discount_status']??0
        ]);
        BusinessSetting::updateOrInsert(['key' => 'new_customer_discount_amount'], [
            'value' => $request['new_customer_discount_amount']??0
        ]);
        BusinessSetting::updateOrInsert(['key' => 'new_customer_discount_amount_type'], [
            'value' => $request['new_customer_discount_amount_type']?? 'percentage'
        ]);
        BusinessSetting::updateOrInsert(['key' => 'new_customer_discount_amount_validity'], [
            'value' => $request['new_customer_discount_amount_validity']??0
        ]);
        BusinessSetting::updateOrInsert(['key' => 'new_customer_discount_validity_type'], [
            'value' => $request['new_customer_discount_validity_type']??'day'
        ]);

        Toastr::success(translate('messages.customer_settings_updated_successfully'));
        return back();
    }

    public function export(Request $request){
        $key = [];
        if ($request->search) {
            $key = explode(' ', $request['search']);
        }
        $customers = User::when(count($key) > 0, function ($query) use ($key) {
            foreach ($key as $value) {
                $query->orWhere('f_name', 'like', "%{$value}%")
                    ->orWhere('l_name', 'like', "%{$value}%")
                    ->orWhere('email', 'like', "%{$value}%")
                    ->orWhere('phone', 'like', "%{$value}%");
            };
        })
        ->orderBy('order_count', 'desc')->get();


        $data = [
            'customers'=>$customers,
            'search'=>$request->search??null,

        ];

        if ($request->type == 'excel') {
            return Excel::download(new CustomerListExport($data), 'Customers.xlsx');
        } else if ($request->type == 'csv') {
            return Excel::download(new CustomerListExport($data), 'Customers.csv');
        }
    }
}
