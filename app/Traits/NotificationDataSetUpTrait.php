<?php

namespace App\Traits;

use App\Models\NotificationSetting;


trait NotificationDataSetUpTrait
{
    public static function getAdminNotificationSetupData(): array
    {
        $data []=[
            'title' => 'forget_password',
            'key' => 'forget_password',
            'type' => 'admin',
            'mail_status' => 'active',
            'sms_status' => 'active',
            'push_notification_status' => 'disable',
            'sub_title' => 'Sent_notification_on_forget_password',
        ];
        $data []=[
            'title' => 'deliveryman_self_registration',
            'key' => 'deliveryman_self_registration',
            'type' => 'admin',
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'disable',
            'sub_title' => 'Sent_notification_on_deliveryman_self_registration',
        ];
        $data []=[
            'title' => 'store_self_registration',
            'key' => 'store_self_registration',
            'type' => 'admin',
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'disable',
            'sub_title' => 'Sent_notification_on_store_self_registration',
        ];
        $data []=[
            'title' => 'campaign_join_request',
            'key' => 'campaign_join_request',
            'type' => 'admin',
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'disable',
            'sub_title' => 'Sent_notification_on_campaign_join_request',
        ];
        $data []=[
            'title' => 'withdraw_request',
            'key' => 'withdraw_request',
            'type' => 'admin',
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'disable',
            'sub_title' => 'Sent_notification_on_withdraw_request',
        ];
        $data []=[
            'title' => 'order_refund_request',
            'key' => 'order_refund_request',
            'type' => 'admin',
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'disable',
            'sub_title' => 'Sent_notification_on_order_refund_request',
        ];

        $data []=[
            'title' => 'advertisement_add',
            'key' => 'advertisement_add',
            'type' => 'admin',
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'disable',
            'sub_title' => 'Sent_notification_on_advertisement_add',
        ];
        $data []=[
            'title' => 'advertisement_update',
            'key' => 'advertisement_update',
            'type' => 'admin',
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'disable',
            'sub_title' => 'Sent_notification_on_advertisement_update',
        ];

        //delivery man

        $data []=[
            'title' => 'deliveryman_registration',
            'key' => 'deliveryman_registration',
            'type' => 'deliveryman',
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'disable',
            'sub_title' => 'Sent_notification_on_deliveryman_registration',
        ];
        $data []=[
            'title' => 'deliveryman_registration_approval',
            'key' => 'deliveryman_registration_approval',
            'type' => 'deliveryman',
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'disable',
            'sub_title' => 'Sent_notification_on_deliveryman_registration_approval',
        ];
        $data []=[
            'title' => 'deliveryman_registration_deny',
            'key' => 'deliveryman_registration_deny',
            'type' => 'deliveryman',
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'disable',
            'sub_title' => 'Sent_notification_on_deliveryman_registration_deny',
        ];
        $data []=[
            'title' => 'deliveryman_account_block',
            'key' => 'deliveryman_account_block',
            'type' => 'deliveryman',
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'active',
            'sub_title' => 'Sent_notification_on_deliveryman_account_block',
        ];
        $data []=[
            'title' => 'deliveryman_account_unblock',
            'key' => 'deliveryman_account_unblock',
            'type' => 'deliveryman',
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'active',
            'sub_title' => 'Sent_notification_on_deliveryman_account_unblock',
        ];
        $data []=[
            'title' => 'deliveryman_forget_password',
            'key' => 'deliveryman_forget_password',
            'type' => 'deliveryman',
            'mail_status' => 'active',
            'sms_status' => 'active',
            'push_notification_status' => 'disable',
            'sub_title' => 'Sent_notification_on_deliveryman_forget_password',
        ];
        $data []=[
            'title' => 'deliveryman_collect_cash',
            'key' => 'deliveryman_collect_cash',
            'type' => 'deliveryman',
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'active',
            'sub_title' => 'Sent_notification_on_deliveryman_collect_cash',
        ];

        $data []=[
            'title' => 'deliveryman_order_notification',
            'key' => 'deliveryman_order_notification',
            'type' => 'deliveryman',
            'mail_status' => 'disable',
            'sms_status' => 'disable',
            'push_notification_status' => 'active',
            'sub_title' => 'Sent_notification_order_notification_to_deliveryman',
        ];
        $data []=[
            'title' => 'deliveryman_order_assign_or_unassign',
            'key' => 'deliveryman_order_assign_unassign',
            'type' => 'deliveryman',
            'mail_status' => 'disable',
            'sms_status' => 'disable',
            'push_notification_status' => 'active',
            'sub_title' => 'Sent_notification_on_deliveryman_order_assign_or_unassign',
        ];



        // store

        $data []=[
            'title' => 'store_registration',
            'key' => 'store_registration',
            'type' => 'store',
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'disable',
            'sub_title' => 'Sent_notification_on_store_registration',
        ];
        $data []=[
            'title' => 'store_registration_approval',
            'key' => 'store_registration_approval',
            'type' => 'store',
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'disable',
            'sub_title' => 'Sent_notification_on_store_registration_approval',
        ];
        $data []=[
            'title' => 'store_registration_deny',
            'key' => 'store_registration_deny',
            'type' => 'store',
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'disable',
            'sub_title' => 'Sent_notification_on_store_registration_deny',
        ];
        $data []=[
            'title' => 'store_account_block',
            'key' => 'store_account_block',
            'type' => 'store',
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'active',
            'sub_title' => 'Sent_notification_on_store_account_block',
        ];
        $data []=[
            'title' => 'store_account_unblock',
            'key' => 'store_account_unblock',
            'type' => 'store',
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'active',
            'sub_title' => 'Sent_notification_on_store_account_unblock',
        ];
        $data []=[
            'title' => 'store_withdraw_approve',
            'key' => 'store_withdraw_approve',
            'type' => 'store',
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'active',
            'sub_title' => 'Sent_notification_on_store_withdraw_approve',
        ];
        $data []=[
            'title' => 'store_withdraw_rejaction',
            'key' => 'store_withdraw_rejaction',
            'type' => 'store',
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'active',
            'sub_title' => 'Sent_notification_on_store_withdraw_rejaction',
        ];
        $data []=[
            'title' => 'store_campaign_join_request',
            'key' => 'store_campaign_join_request',
            'type' => 'store',
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'disable',
            'sub_title' => 'Sent_notification_on_store_campaign_join_request',
        ];
        $data []=[
            'title' => 'store_campaign_join_rejaction',
            'key' => 'store_campaign_join_rejaction',
            'type' => 'store',
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'active',
            'sub_title' => 'Sent_notification_on_store_campaign_join_rejaction',
        ];
        $data []=[
            'title' => 'store_campaign_join_approval',
            'key' => 'store_campaign_join_approval',
            'type' => 'store',
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'active',
            'sub_title' => 'Sent_notification_on_store_campaign_join_approval',
        ];
        $data []=[
            'title' => 'store_order_notification',
            'key' => 'store_order_notification',
            'type' => 'store',
            'mail_status' => 'disable',
            'sms_status' => 'disable',
            'push_notification_status' => 'active',
            'sub_title' => 'Sent_notification_on_store_order_notification',
        ];

        $data []=[
            'title' => 'store_product_approve',
            'key' => 'store_product_approve',
            'type' => 'store',
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'active',
            'sub_title' => 'Sent_notification_on_store_product_approve',
        ];
        $data []=[
            'title' => 'store_product_reject',
            'key' => 'store_product_reject',
            'type' => 'store',
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'active',
            'sub_title' => 'Sent_notification_on_store_product_reject',
        ];
        $data []=[
            'title' => 'store_subscription_success',
            'key' => 'store_subscription_success',
            'type' => 'store',
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'active',
            'sub_title' => 'Sent_notification_on_store_subscription_success',
        ];
        $data []=[
            'title' => 'store_subscription_renew',
            'key' => 'store_subscription_renew',
            'type' => 'store',
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'active',
            'sub_title' => 'Sent_notification_on_store_subscription_renew',
        ];
        $data []=[
            'title' => 'store_subscription_shift',
            'key' => 'store_subscription_shift',
            'type' => 'store',
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'active',
            'sub_title' => 'Sent_notification_on_store_subscription_shift',
        ];
        $data []=[
            'title' => 'store_subscription_cancel',
            'key' => 'store_subscription_cancel',
            'type' => 'store',
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'active',
            'sub_title' => 'Sent_notification_on_store_subscription_cancel',
        ];
        $data []=[
            'title' => 'store_subscription_plan_update',
            'key' => 'store_subscription_plan_update',
            'type' => 'store',
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'inactive',
            'sub_title' => 'Sent_notification_on_store_subscription_plan_update',
        ];


        $data []=[
            'title' => 'store_advertisement_create_by_admin',
            'key' => 'store_advertisement_create_by_admin',
            'type' => 'store',
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'active',
            'sub_title' => 'Sent_notification_on_store_advertisement_create_by_admin',
        ];
        $data []=[
            'title' => 'store_advertisement_approval',
            'key' => 'store_advertisement_approval',
            'type' => 'store',
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'active',
            'sub_title' => 'Sent_notification_on_store_advertisement_approval',
        ];
        $data []=[
            'title' => 'store_advertisement_deny',
            'key' => 'store_advertisement_deny',
            'type' => 'store',
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'active',
            'sub_title' => 'Sent_notification_on_store_advertisement_deny',
        ];
        $data []=[
            'title' => 'store_advertisement_resume',
            'key' => 'store_advertisement_resume',
            'type' => 'store',
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'active',
            'sub_title' => 'Sent_notification_on_store_advertisement_resume',
        ];
        $data []=[
            'title' => 'store_advertisement_pause',
            'key' => 'store_advertisement_pause',
            'type' => 'store',
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'active',
            'sub_title' => 'Sent_notification_on_store_advertisement_pause',
        ];

        // Customer
        $data []=[
            'title' => 'customer_registration',
            'key' => 'customer_registration',
            'type' => 'customer',
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'disable',
            'sub_title' => 'Sent_notification_on_customer_registration',
        ];
        $data []=[
            'title' => 'customer_pos_registration',
            'key' => 'customer_pos_registration',
            'type' => 'customer',
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'disable',
            'sub_title' => 'Sent_notification_on_customer_pos_registration',
        ];
        $data []=[
            'title' => 'customer_registration_otp',
            'key' => 'customer_registration_otp',
            'type' => 'customer',
            'mail_status' => 'active',
            'sms_status' => 'active',
            'push_notification_status' => 'disable',
            'sub_title' => 'Sent_notification_on_customer_registration_otp',
        ];
        $data []=[
            'title' => 'customer_login_otp',
            'key' => 'customer_login_otp',
            'type' => 'customer',
            'mail_status' => 'active',
            'sms_status' => 'active',
            'push_notification_status' => 'disable',
            'sub_title' => 'Sent_notification_on_customer_login_otp',
        ];
        $data []=[
            'title' => 'customer_forget_password',
            'key' => 'customer_forget_password',
            'type' => 'customer',
            'mail_status' => 'active',
            'sms_status' => 'active',
            'push_notification_status' => 'disable',
            'sub_title' => 'Sent_notification_on_customer_forget_password',
        ];

        $data []=[
            'title' => 'customer_order_notification',
            'key' => 'customer_order_notification',
            'type' => 'customer',
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'active',
            'sub_title' => 'Sent_notification_on_customer_order_notification',
        ];

        $data []=[
            'title' => 'customer_delivery_verification',
            'key' => 'customer_delivery_verification',
            'type' => 'customer',
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'active',
            'sub_title' => 'Sent_notification_on_customer_delivery_verification',
        ];

        $data []=[
            'title' => 'customer_refund_request_approval',
            'key' => 'customer_refund_request_approval',
            'type' => 'customer',
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'active',
            'sub_title' => 'Sent_notification_on_customer_refund_request_approval',
        ];
        $data []=[
            'title' => 'customer_refund_request_rejaction',
            'key' => 'customer_refund_request_rejaction',
            'type' => 'customer',
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'active',
            'sub_title' => 'Sent_notification_on_customer_refund_request_rejaction',
        ];
        $data []=[
            'title' => 'customer_add_fund_to_wallet',
            'key' => 'customer_add_fund_to_wallet',
            'type' => 'customer',
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'active',
            'sub_title' => 'Sent_notification_on_customer_add_fund_to_wallet',
        ];
        $data []=[
            'title' => 'customer_offline_payment_approve',
            'key' => 'customer_offline_payment_approve',
            'type' => 'customer',
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'active',
            'sub_title' => 'Sent_notification_on_customer_offline_payment_approve',
        ];
        $data []=[
            'title' => 'customer_offline_payment_deny',
            'key' => 'customer_offline_payment_deny',
            'type' => 'customer',
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'active',
            'sub_title' => 'Sent_notification_on_customer_offline_payment_deny',
        ];
        $data []=[
            'title' => 'customer_account_block',
            'key' => 'customer_account_block',
            'type' => 'customer',
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'active',
            'sub_title' => 'Sent_notification_on_customer_account_block',
        ];
        $data []=[
            'title' => 'customer_account_unblock',
            'key' => 'customer_account_unblock',
            'type' => 'customer',
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'active',
            'sub_title' => 'Sent_notification_on_customer_account_unblock',
        ];
        $data []=[
            'title' => 'customer_cashback',
            'key' => 'customer_cashback',
            'type' => 'customer',
            'mail_status' => 'disable',
            'sms_status' => 'disable',
            'push_notification_status' => 'active',
            'sub_title' => 'Sent_notification_on_customer_cashback',
        ];
        $data []=[
            'title' => 'customer_referral_bonus_earning',
            'key' => 'customer_referral_bonus_earning',
            'type' => 'customer',
            'mail_status' => 'disable',
            'sms_status' => 'disable',
            'push_notification_status' => 'active',
            'sub_title' => 'Sent_notification_on_customer_referral_bonus_earning',
        ];
        $data []=[
            'title' => 'customer_new_referral_join',
            'key' => 'customer_new_referral_join',
            'type' => 'customer',
            'mail_status' => 'disable',
            'sms_status' => 'disable',
            'push_notification_status' => 'active',
            'sub_title' => 'Sent_notification_on_customer_new_referral_join',
        ];

        return $data;
    }
    public static function getStoreNotificationSetupData($id): array
    {
        $data []=[
            'title' => 'account_block',
            'key' => 'store_account_block',
            'store_id' => $id,
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'active',
            'sub_title' => 'Get_notification_on_account_block',
        ];
        $data []=[
            'title' => 'account_unblock',
            'key' => 'store_account_unblock',
            'store_id' => $id,
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'active',
            'sub_title' => 'Get_notification_on_account_unblock',
        ];
        $data []=[
            'title' => 'withdraw_approve',
            'key' => 'store_withdraw_approve',
            'store_id' => $id,
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'active',
            'sub_title' => 'Get_notification_on_withdraw_approve',
        ];
        $data []=[
            'title' => 'withdraw_rejaction',
            'key' => 'store_withdraw_rejaction',
            'store_id' => $id,
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'active',
            'sub_title' => 'Get_notification_on_withdraw_rejaction',
        ];
        $data []=[
            'title' => 'campaign_join_request',
            'key' => 'store_campaign_join_request',
            'store_id' => $id,
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'disable',
            'sub_title' => 'Get_notification_on_campaign_join_request',
        ];
        $data []=[
            'title' => 'campaign_join_rejaction',
            'key' => 'store_campaign_join_rejaction',
            'store_id' => $id,
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'active',
            'sub_title' => 'Get_notification_on_campaign_join_rejaction',
        ];
        $data []=[
            'title' => 'campaign_join_approval',
            'key' => 'store_campaign_join_approval',
            'store_id' => $id,
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'active',
            'sub_title' => 'Get_notification_on_campaign_join_approval',
        ];
        $data []=[
            'title' => 'order_notification',
            'key' => 'store_order_notification',
            'store_id' => $id,
            'mail_status' => 'disable',
            'sms_status' => 'disable',
            'push_notification_status' => 'active',
            'sub_title' => 'Get_notification_on_order_notification',
        ];

        $data []=[
            'title' => 'advertisement_create_by_admin',
            'key' => 'store_advertisement_create_by_admin',
            'store_id' => $id,
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'active',
            'sub_title' => 'Get_notification_on_advertisement_create_by_admin',
        ];
        $data []=[
            'title' => 'advertisement_approval',
            'key' => 'store_advertisement_approval',
            'store_id' => $id,
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'active',
            'sub_title' => 'Get_notification_on_advertisement_approval',
        ];
        $data []=[
            'title' => 'advertisement_deny',
            'key' => 'store_advertisement_deny',
            'store_id' => $id,
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'active',
            'sub_title' => 'Get_notification_on_advertisement_deny',
        ];
        $data []=[
            'title' => 'advertisement_resume',
            'key' => 'store_advertisement_resume',
            'store_id' => $id,
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'active',
            'sub_title' => 'Get_notification_on_advertisement_resume',
        ];
        $data []=[
            'title' => 'advertisement_pause',
            'key' => 'store_advertisement_pause',
            'store_id' => $id,
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'active',
            'sub_title' => 'Get_notification_on_advertisement_pause',
        ];

        $data []=[
            'title' => 'product_approve',
            'key' => 'store_product_approve',
            'store_id' => $id,
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'active',
            'sub_title' => 'Get_notification_on_product_approve',
        ];
        $data []=[
            'title' => 'product_reject',
            'key' => 'store_product_reject',
            'store_id' => $id,
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'active',
            'sub_title' => 'Get_notification_on_product_reject',
        ];
        $data []=[
            'title' => 'subscription_success',
            'key' => 'store_subscription_success',
            'store_id' => $id,
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'active',
            'sub_title' => 'Get_notification_on_subscription_success',
        ];
        $data []=[
            'title' => 'subscription_renew',
            'key' => 'store_subscription_renew',
            'store_id' => $id,
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'active',
            'sub_title' => 'Get_notification_on_subscription_renew',
        ];
        $data []=[
            'title' => 'subscription_shift',
            'key' => 'store_subscription_shift',
            'store_id' => $id,
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'active',
            'sub_title' => 'Get_notification_on_subscription_shift',
        ];
        $data []=[
            'title' => 'subscription_cancel',
            'key' => 'store_subscription_cancel',
            'store_id' => $id,
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'active',
            'sub_title' => 'Get_notification_on_subscription_cancel',
        ];
        $data []=[
            'title' => 'subscription_plan_update',
            'key' => 'store_subscription_plan_update',
            'store_id' => $id,
            'mail_status' => 'active',
            'sms_status' => 'disable',
            'push_notification_status' => 'active',
            'sub_title' => 'Get_notification_on_subscription_plan_update',
        ];

        return $data;
    }


    public static function updateAdminNotificationSetupData(){



        $data []=[
            'title' => 'customer_registration_otp',
            'key' => 'customer_registration_otp',
            'type' => 'customer',
            'mail_status' => 'active',
            'sms_status' => 'active',
            'push_notification_status' => 'disable',
            'sub_title' => 'Sent_notification_on_customer_registration_otp',
        ];
        $data []=[
            'title' => 'customer_login_otp',
            'key' => 'customer_login_otp',
            'type' => 'customer',
            'mail_status' => 'active',
            'sms_status' => 'active',
            'push_notification_status' => 'disable',
            'sub_title' => 'Sent_notification_on_customer_login_otp',
        ];
        $data []=[
            'title' => 'customer_forget_password',
            'key' => 'customer_forget_password',
            'type' => 'customer',
            'mail_status' => 'active',
            'sms_status' => 'active',
            'push_notification_status' => 'disable',
            'sub_title' => 'Sent_notification_on_customer_forget_password',
        ];

        $data []=[
            'title' => 'deliveryman_forget_password',
            'key' => 'deliveryman_forget_password',
            'type' => 'deliveryman',
            'mail_status' => 'active',
            'sms_status' => 'active',
            'push_notification_status' => 'disable',
            'sub_title' => 'Sent_notification_on_deliveryman_forget_password',
        ];

            foreach($data as $item){
                NotificationSetting::where('key', $item['key'])->where('type' , $item['type'] )->update([
                    'push_notification_status' => $item['push_notification_status']
                ]);
            }
            return true;
    }






}
