@extends('layouts.vendor.app')

@section('title',translate('messages.item_list'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
@php($store_data=\App\CentralLogics\Helpers::get_store_data())
    <div class="content container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <div class="btn--container align-items-center mb-0">
                <div class="mr-auto">
                    <h1 class="page-header-title"><i class="tio-filter-list"></i> {{translate('messages.item_list')}}<span class="badge badge-soft-dark ml-2" id="itemCount">{{$items->total()}}</span></h1>
                </div>


            </div>
        </div>
        <!-- End Page Header -->


        <!-- End Page Header -->
        <div class="card mb-3">
            <!-- Header -->
            <div class="card-header py-2 border-0">
                <h1>{{ translate('search_data') }}</h1>
            </div>
                <div class="row mr-1 ml-2 mb-5">

                    <div class="col-sm-6 col-md-4">
                        <div class="select-item">
                            <select name="category_id" id="category" data-placeholder="{{ translate('messages.select_category') }}"
                                class="js-data-example-ajax form-control set-filter" id="category_id"
                                data-url="{{url()->full()}}" data-filter="category_id">
                                @if($category)
                                <option value="{{$category->id}}" selected>{{$category->name}}</option>
                                @else
                                <option value="all" selected>{{translate('messages.all_category')}}</option>
                                @endif
                            </select>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-4">
                        <div class="select-item">
                            <select name="sub_category_id" class="form-control js-select2-custom set-filter" data-placeholder="{{ translate('messages.select_sub_category') }}" id="sub-categories" data-url="{{url()->full()}}" data-filter="sub_category_id">
                                <option value="all" selected>{{translate('messages.all_sub_category')}}</option>
                                @foreach($sub_categories as $z)
                                <option
                                    value="{{$z['id']}}" {{ request()?->sub_category_id == $z['id']?'selected':''}}>
                                    {{$z['name']}}
                                </option>
                            @endforeach
                            </select>
                        </div>
                    </div>


                    @if (($store_data->module->module_type == 'food') && $toggle_veg_non_veg)
                    <!-- Veg/NonVeg filter -->

                <div class="col-sm-6 col-md-4">
                    <div class="select-item">
                        <select name="category_id" data-url="{{url()->full()}}" data-filter="type" data-placeholder="{{translate('messages.all')}}" class="form-control max-lg-h-40px set-filter">
                            <option value="all" {{$type=='all'?'selected':''}}>{{translate('messages.all')}}</option>
                            <option value="veg" {{$type=='veg'?'selected':''}}>{{translate('messages.veg')}}</option>
                            <option value="non_veg" {{$type=='non_veg'?'selected':''}}>{{translate('messages.non_veg')}}</option>
                        </select>
                    </div>
                </div>
                    <!-- End Veg/NonVeg filter -->
                    @endif
                </div>
            </div>


        <!-- Card -->
        <div class="card">
            <!-- Header -->
            <div class="card-header py-2  border-0">
                <div class="search--button-wrapper justify-content-end">
                    <form id="search-form" class="search-form">
                    @csrf
                        <!-- Search -->
                        <div class="input-group input--group">
                            <input id="datatableSearch" type="search" name="search" class="form-control" placeholder="{{translate('messages.ex_search_name')}}" aria-label="{{translate('messages.search_here')}}">
                            <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                        </div>
                        <!-- End Search -->
                    </form>
                    <!-- End Unfold -->
                    <div>
                        <a href="{{route('vendor.item.add-new')}}" class="btn btn--primary m-0 pull-right"><i
                                    class="tio-add-circle"></i> {{translate('messages.add_new_item')}}</a>
                    </div>
                </div>
            </div>
            <!-- End Header -->


            <!-- Table -->
            <div class="table-responsive datatable-custom">
                <table id="datatable" class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table"
                    data-hs-datatables-options='{
                        "columnDefs": [{
                            "targets": [],
                            "width": "5%",
                            "orderable": false
                        }],
                        "order": [],
                        "info": {
                        "totalQty": "#datatableWithPaginationInfoTotalQty"
                        },

                        "entries": "#datatableEntries",
                        "isResponsive": false,
                        "isShowPaging": false,
                            "paging":false
                    }'>
                    <thead class="thead-light">
                        <tr>
                            <th class="border-0">{{translate('messages.#')}}</th>
                            <th class="border-0 w-20p">{{translate('messages.name')}}</th>
                            <th class="border-0 w-20p">{{translate('messages.category')}}</th>
                            <th class="border-0">{{translate('messages.price')}}</th>
                            <th class="border-0 text-center">{{translate('messages.Recommended')}}</th>
                            <th class="border-0 text-center">{{translate('messages.status')}}</th>
                            <th class="border-0 text-center">{{translate('messages.action')}}</th>
                        </tr>
                    </thead>

                    <tbody id="set-rows">
                    @foreach($items as $key=>$item)
                        <tr>
                            <td>{{$key+$items->firstItem()}}</td>
                            <td>
                                <a class="media align-items-center" href="{{route('vendor.item.view',[$item['id']])}}">
                                    <img class="avatar avatar-lg mr-3 onerror-image" src="{{ $item['image_full_url'] }}"
                                         data-onerror-image="{{asset('public/assets/admin/img/160x160/img2.jpg')}}" alt="{{$item->name}} image">
                                    <div class="media-body">
                                        <h5 class="text-hover-primary mb-0">{{Str::limit($item['name'],20,'...')}}</h5>
                                    </div>
                                </a>
                            </td>
                            <td>
                            {{Str::limit($item->category?$item->category->name:translate('messages.category_deleted'),20,'...')}}
                            </td>
                            <td>
                                <div class="mw--85px">
                                    {{\App\CentralLogics\Helpers::format_currency($item['price'])}}
                                </div>
                            </td>
                            <td>
                                <div class="d-flex">
                                    <div class="mx-auto">
                                        <label class="toggle-switch toggle-switch-sm mr-2"  data-toggle="tooltip" data-placement="top" title="{{ translate('messages.Recommend_to_customers') }}" for="recCheckbox{{$item->id}}">
                                            <input type="checkbox" data-url="{{route('vendor.item.recommended',[$item['id'],$item->recommended?0:1])}}" class="toggle-switch-input redirect-url" id="recCheckbox{{$item->id}}" {{$item->recommended?'checked':''}}>
                                            <span class="toggle-switch-label">
                                                <span class="toggle-switch-indicator"></span>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <label class="toggle-switch toggle-switch-sm" for="stocksCheckbox{{$item->id}}">
                                    <input type="checkbox" data-url="{{route('vendor.item.status',[$item['id'],$item->status?0:1])}}" class="toggle-switch-input redirect-url" id="stocksCheckbox{{$item->id}}" {{$item->status?'checked':''}}>
                                    <span class="toggle-switch-label mx-auto">
                                        <span class="toggle-switch-indicator"></span>
                                    </span>
                                </label>
                            </td>
                            <td>
                                <div class="btn--container justify-content-center">
                                    <a class="btn btn-sm btn--primary btn-outline-primary action-btn"
                                        href="{{route('vendor.item.edit',[$item['id']])}}" title="{{translate('messages.edit_item')}}"><i class="tio-edit"></i>
                                    </a>
                                    <a class="btn btn-sm btn--danger btn-outline-danger action-btn form-alert" href="javascript:"
                                        data-id="food-{{$item['id']}}" data-message="{{ translate('Want to delete this item ?') }}" title="{{translate('messages.delete_item')}}"><i class="tio-delete-outlined"></i>
                                    </a>
                                </div>
                                <form action="{{route('vendor.item.delete',[$item['id']])}}"
                                        method="post" id="food-{{$item['id']}}">
                                    @csrf @method('delete')
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                <hr>
                <div class="page-area">
                    <table>
                        <tfoot class="border-top">
                        {!! $items->links() !!}
                        </tfoot>
                    </table>
                </div>
                @if(count($items) === 0)
                <div class="empty--data">
                    <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="public">
                    <h5>
                        {{translate('no_data_found')}}
                    </h5>
                </div>
                @endif
            </div>
            <!-- End Table -->
        </div>
        <!-- End Card -->
    </div>
</div>
@endsection

@push('script_2')
    <script>
        "use strict";
        $(document).on('ready', function () {
        // INITIALIZATION OF DATATABLES
        // =======================================================
        let datatable = $.HSCore.components.HSDatatables.init($('#datatable'), {
          select: {
            style: 'multi',
            classMap: {
              checkAll: '#datatableCheckAll',
              counter: '#datatableCounter',
              counterInfo: '#datatableCounterInfo'
            }
          },
          language: {
            zeroRecords: '<div class="text-center p-4">' +
                '<img class="w-7rem mb-3" src="{{asset('public/assets/admin/svg/illustrations/sorry.svg')}}" alt="Image Description">' +

                '</div>'
          }
        });

        $('#datatableSearch').on('mouseup', function (e) {
          let $input = $(this),
            oldValue = $input.val();

          if (oldValue == "") return;

          setTimeout(function(){
            let newValue = $input.val();

            if (newValue == ""){
              // Gotcha
              datatable.search('').draw();
            }
          }, 1);
        });

        $('#toggleColumn_index').change(function (e) {
          datatable.columns(0).visible(e.target.checked)
        })
        $('#toggleColumn_name').change(function (e) {
          datatable.columns(1).visible(e.target.checked)
        })

        $('#toggleColumn_type').change(function (e) {
          datatable.columns(2).visible(e.target.checked)
        })

        $('#toggleColumn_status').change(function (e) {
          datatable.columns(4).visible(e.target.checked)
        })
        $('#toggleColumn_price').change(function (e) {
          datatable.columns(3).visible(e.target.checked)
        })
        $('#toggleColumn_action').change(function (e) {
          datatable.columns(5).visible(e.target.checked)
        })


            // INITIALIZATION OF SELECT2
            // =======================================================
            $('.js-select2-custom').each(function () {
                let select2 = $.HSCore.components.HSSelect2.init($(this));
            });
        });

        $('#category').select2({
            ajax: {
                url: '{{route("vendor.category.get-all")}}',
                data: function (params) {
                    return {
                        q: params.term, // search term
                        all:true,
                        page: params.page
                    };
                },
                processResults: function (data) {
                    return {
                    results: data
                    };
                },
                __port: function (params, success, failure) {
                    let $request = $.ajax(params);

                    $request.then(success);
                    $request.fail(failure);

                    return $request;
                }
            }
        });

        $('#search-form').on('submit', function (e) {
            e.preventDefault();
            let formData = new FormData(this);
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
            $.post({
                url: '{{route('vendor.item.search')}}',
                data: formData,
                cache: false,
                contentType: false,
                processData: false,
                beforeSend: function () {
                    $('#loading').show();
                },
                success: function (data) {
                    $('#set-rows').html(data.view);
                    $('#itemCount').html(data.count);
                    $('.page-area').hide();
                },
                complete: function () {
                    $('#loading').hide();
                },
            });
        });
    </script>
@endpush
