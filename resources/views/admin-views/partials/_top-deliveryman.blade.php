<!-- Header -->
<div class="card-header border-0 order-header-shadow">
    <h5 class="card-title d-flex justify-content-between">
        <span>{{translate('messages.top_deliveryman')}}</span>
    </h5>
    @php($params=session('dash_params'))
    @if($params['zone_id']!='all')
        @php($zone_name=\App\Models\Zone::where('id',$params['zone_id'])->first()->name)
    @else
        @php($zone_name = translate('messages.all'))
    @endif
    {{--<label class="badge badge-soft-primary">{{translate('messages.zone')}} : {{$zone_name}}</label>--}}
        <a href="{{ route('admin.users.delivery-man.list') }}" class="fz-12px font-medium text-006AE5">{{translate('view_all')}}</a>
</div>
<!-- End Header -->
<!-- Body -->
<div class="card-body">
    <div class="top--selling">
        @forelse($top_deliveryman as $key=>$item)

            <a class="grid--card" href="{{route('admin.users.delivery-man.preview',[$item['id']])}}">
                <img class="onerror-image" data-onerror-image="{{asset('public/assets/admin/img/admin.png')}}"
                src="{{ $item['image_full_url'] ?? asset('public/assets/admin/img/admin.png') }}" alt="{{$item['f_name']}}" >
                <div class="cont pt-2">
                    <h6 class="mb-1">{{$item['f_name']??'Not exist'}}</h6>
                    <span>{{$item['phone']}}</span>
                </div>
                <div class="ml-auto">
                    <span class="badge badge-soft">{{ translate('messages.orders') }} : {{$item['orders_count']}}</span>
                </div>
            </a>

            @empty
         
            @endforelse
    </div>
</div>
<!-- End Body -->
<script src="{{asset('public/assets/admin')}}/js/view-pages/common.js"></script>
