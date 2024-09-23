<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Models\User;
use Carbon\CarbonInterval;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use Illuminate\Support\Carbon;
use App\Models\BusinessSetting;
use App\CentralLogics\SMS_module;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Mail;
use Modules\Gateways\Traits\SmsGateway;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class PasswordResetController extends Controller
{
    public function reset_password_request(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $firebase_otp_verification = BusinessSetting::where('key', 'firebase_otp_verification')->first()->value??0;

        $customer = User::Where(['phone' => $request['phone']])->first();

        if (isset($customer)) {
            if($firebase_otp_verification || env('APP_MODE')=='demo')
            {
                return response()->json(['message' => translate('messages.otp_sent_successfull')], 200);
            }

            $otp_interval_time= 60; //seconds
            $password_verification_data= DB::table('password_resets')->where('email', $customer['email'])->first();
            if(isset($password_verification_data) &&  Carbon::parse($password_verification_data->created_at)->DiffInSeconds() < $otp_interval_time){
                $time= $otp_interval_time - Carbon::parse($password_verification_data->created_at)->DiffInSeconds();
                $errors = [];
                array_push($errors, ['code' => 'otp', 'message' =>  translate('messages.please_try_again_after_').$time.' '.translate('messages.seconds')]);
                return response()->json([
                    'errors' => $errors
                ], 405);
            }

            $token = rand(100000, 999999);
            DB::table('password_resets')->updateOrInsert(['email' => $customer->email],
            [
                'token' => $token,
                'created_at' => now(),
            ]);




            try {
                $mailResponse=null;
                    if ( Helpers::getNotificationStatusData('customer','customer_forget_password','mail_status') && config('mail.status') && Helpers::get_mail_status('forget_password_mail_status_user') == '1') {
                        Mail::to($customer['email'])->send(new \App\Mail\UserPasswordResetMail($token,$customer['f_name']));
                    $mailResponse='success';
                        }
                } catch (\Throwable $th) {
                    $mailResponse=null;
                    info($th->getMessage());
                }


            //for payment and sms gateway addon
            $response =null;
            if(Helpers::getNotificationStatusData('customer','customer_forget_password','sms_status')){
                $published_status = addon_published_status('Gateways');
                if($published_status == 1){
                $response = SmsGateway::send($request['phone'],$token);
                }else{
                    $response = SMS_module::send($request['phone'],$token);
                }
            }

//                if(Helpers::getNotificationStatusData('customer','customer_forget_password','push_notification_status')){
//                    if (isset($request->cm_firebase_token)) {
//                        $data = [
//                            'title' => translate('messages.password_reset'),
//                            'description' => translate('messages.your_reset_password_otp_is').' '.$token,
//                            'order_id' => '',
//                            'image' => '',
//                            'type' => 'otp'
//                        ];
//                        Helpers::send_push_notif_to_device($request->cm_firebase_token, $data);
//
//                        DB::table('user_notifications')->insert([
//                            'data' => json_encode($data),
//                            'user_id' => $customer->id,
//                            'created_at' => now(),
//                            'updated_at' => now()
//                        ]);
//                        $response = 'success';
//                    }
//                }



                if($response == 'success' && $mailResponse == 'success')
                {
                    return response()->json(['message' => translate('messages.Otp_Successfully_Sent_To_Your_Phone_and_Mail')], 200);
                }
                elseif($response == 'success')
                {
                    return response()->json(['message' => translate('messages.Otp_Successfully_Sent_To_Your_Phone')], 200);
                }
                elseif($mailResponse == 'success')
                {
                    return response()->json(['message' => translate('messages.Otp_Successfully_Sent_To_Your_Mail')], 200);
                }
                else
                {
                    return response()->json([
                        'errors' => [
                            ['code' => 'otp', 'message' => translate('messages.failed_to_send_sms')]
                    ]], 405);
                }


        }
        return response()->json(['errors' => [
            ['code' => 'not-found', 'message' => 'Phone number not found!']
        ]], 404);
    }

    public function verify_token(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|regex:/^([0-9\s\-\+\(\)]*)$/|min:10',
            'reset_token'=> 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $user=User::where('phone', $request->phone)->first();
        if (!isset($user)) {
            return response()->json(['errors' => [
                ['code' => 'not-found', 'message' => 'Phone number not found!']
            ]], 404);
        }

        if(env('APP_MODE')=='demo')
        {
            if($request['reset_token']=="123456")
            {
                return response()->json(['message'=>"OTP found, you can proceed"], 200);
            }
            return response()->json(['errors' => [
                ['code' => 'invalid', 'message' => 'Invalid OTP.']
            ]], 400);
        }

        $data = DB::table('password_resets')->where(['token' => $request['reset_token'],'email'=>$user->email])->first();
        if (isset($data)) {
            return response()->json(['message'=>"OTP found, you can proceed"], 200);
        } else{
            // $otp_hit = BusinessSetting::where('key', 'max_otp_hit')->first();
            // $max_otp_hit =isset($otp_hit) ? $otp_hit->value : 5 ;
            $max_otp_hit = 5;
            // $otp_hit_time = BusinessSetting::where('key', 'max_otp_hit_time')->first();
            // $max_otp_hit_time = isset($otp_hit_time) ? $otp_hit_time->value : 30 ;
            $max_otp_hit_time = 60; // seconds
            $temp_block_time = 600; // seconds
            $verification_data= DB::table('password_resets')->where('email', $user->email)->first();

            if(isset($verification_data)){
                $time= $temp_block_time - Carbon::parse($verification_data->temp_block_time)->DiffInSeconds();

                if(isset($verification_data->temp_block_time ) && Carbon::parse($verification_data->temp_block_time)->DiffInSeconds() <= $temp_block_time){
                    $time= $temp_block_time - Carbon::parse($verification_data->temp_block_time)->DiffInSeconds();

                    $errors = [];
                    array_push($errors, ['code' => 'otp_block_time', 'message' => translate('messages.please_try_again_after_').CarbonInterval::seconds($time)->cascade()->forHumans()
                ]);
                return response()->json([
                    'errors' => $errors
                ], 405);
                }

                if($verification_data->is_temp_blocked == 1 && Carbon::parse($verification_data->created_at)->DiffInSeconds() >= $max_otp_hit_time){
                    DB::table('password_resets')->updateOrInsert(['email' => $user->email],
                        [
                            'otp_hit_count' => 0,
                            'is_temp_blocked' => 0,
                            'temp_block_time' => null,
                            'created_at' => now(),
                        ]);
                    }

                if($verification_data->otp_hit_count >= $max_otp_hit &&  Carbon::parse($verification_data->created_at)->DiffInSeconds() < $max_otp_hit_time &&  $verification_data->is_temp_blocked == 0){

                    DB::table('password_resets')->updateOrInsert(['email' => $user->email],
                        [
                        'is_temp_blocked' => 1,
                        'temp_block_time' => now(),
                        'created_at' => now(),
                        ]);
                    $errors = [];
                    array_push($errors, ['code' => 'otp_temp_blocked', 'message' => translate('messages.Too_many_attemps') ]);
                    return response()->json([
                        'errors' => $errors
                    ], 405);
                }
            }


            DB::table('password_resets')->updateOrInsert(['email' => $user->email],
            [
                'otp_hit_count' => DB::raw('otp_hit_count + 1'),
                'created_at' => now(),
                'temp_block_time' => null,
            ]);
        }

        return response()->json(['errors' => [
            ['code' => 'invalid', 'message' => 'Invalid OTP.']
        ]], 400);
    }

    public function reset_password_submit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|regex:/^([0-9\s\-\+\(\)]*)$/|min:10|exists:users,phone',
            'reset_token'=> 'required',
            'password' => ['required', Password::min(8)],
            'confirm_password'=> 'required|same:password',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        if(env('APP_MODE')=='demo')
        {
            if($request['reset_token']=="123456")
            {
                DB::table('users')->where(['phone' => $request['phone']])->update([
                    'password' => bcrypt($request['confirm_password'])
                ]);
                return response()->json(['message' => 'Password changed successfully.'], 200);
            }
            return response()->json([
                'message' => 'Phone number and otp not matched!'
            ], 404);
        }
        $user= User::where(['phone' => $request->phone])->first();
        $data = DB::table('password_resets')->where(['token' => $request['reset_token'], 'email' => $user?->email])->first();

        if (isset($data)) {
            if ($request['password'] == $request['confirm_password']) {
                $user->password = bcrypt($request['confirm_password']);
                $user->save();
                DB::table('password_resets')->where(['token' => $request['reset_token']])->delete();
                return response()->json(['message' => 'Password changed successfully.'], 200);
            }
            return response()->json(['errors' => [
                ['code' => 'mismatch', 'message' => 'Password did,t match!']
            ]], 401);
        }
        return response()->json(['errors' => [
            ['code' => 'invalid', 'message' => translate('messages.invalid_otp')]
        ]], 400);
    }
}
