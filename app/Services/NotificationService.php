<?php

namespace App\Services;

use App\Traits\FileManagerTrait;

class NotificationService
{
    use FileManagerTrait;

    public function getAddData(Object $request): array
    {
        if ($request->has('image')) {
            $imageName = $this->upload('notification/', 'png', $request->file('image'));
        } else {
            $imageName = null;
        }
        return [
            'title' => $request->notification_title,
            'description' => $request->description,
            'image' => $imageName,
            'tergat' => $request->tergat,
            'status' => 1,
            'zone_id' => $request->zone=='all'?null:$request->zone,
        ];
    }
    public function getUpdateData(Object $request, object $notification): array
    {
        if ($request->has('image')) {
            $imageName = $this->updateAndUpload('notification/', $notification->image, 'png', $request->file('image'));
        } else {
            $imageName = $notification['image'];
        }
        return [
            'title' => $request->notification_title,
            'description' => $request->description,
            'image' => $imageName,
            'tergat' => $request->tergat,
            'status' => 1,
            'zone_id' => $request->zone=='all'?null:$request->zone,
            'updated_at' => now(),
        ];
    }

    public function getTopic(Object $request): string
    {
        $topicAllZone =[
            'customer'=>'all_zone_customer',
            'deliveryman'=>'all_zone_delivery_man',
            'store'=>'all_zone_store',
        ];

        $topicZoneWise=[
            'customer'=>'zone_'.$request->zone.'_customer',
            'deliveryman'=>'zone_'.$request->zone.'_delivery_man',
            'store'=>'zone_'.$request->zone.'_store',
        ];

        return $request->zone == 'all'?$topicAllZone[$request->tergat]:$topicZoneWise[$request->tergat];
    }


}
