@extends('layouts.admin.app')

@section('title',translate('messages.Add new category'))

@push('css_or_js')

@endpush

@section('content')
    <div class="content container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/category.png')}}" class="w--20" alt="">
                </span>
                <span>
                    {{translate('add_new_category')}}
                </span>
            </h1>
        </div>
        <!-- End Page Header -->

        <div class="card">
            <div class="card-body">
                <form action="{{isset($category)?route('admin.category.update',[$category['id']]):route('admin.category.store')}}" method="post" enctype="multipart/form-data">
                    @csrf
                    @if($language)
                        <ul class="nav nav-tabs mb-4 border-0">
                            <li class="nav-item">
                                <a class="nav-link lang_link active"
                                href="#"
                                id="default-link">{{translate('messages.default')}}</a>
                            </li>
                            @foreach ($language as $lang)
                                <li class="nav-item">
                                    <a class="nav-link lang_link"
                                        href="#"
                                        id="{{ $lang }}-link">{{ \App\CentralLogics\Helpers::get_language_name($lang) . '(' . strtoupper($lang) . ')' }}</a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    <div class="row">
                        <div class="col-md-6">
                            @if ($language)
                            <div class="form-group lang_form" id="default-form">
                                <label class="input-label" for="exampleFormControlInput1">{{translate('messages.name')}} ({{ translate('messages.default') }})
                                    <span class="form-label-secondary text-danger"
                                    data-toggle="tooltip" data-placement="right"
                                    data-original-title="{{ translate('messages.Required.')}}"> *
                                    </span>

                                </label>
                                <input type="text" name="name[]"  value="{{ old('name.0') }}" class="form-control" placeholder="{{translate('messages.new_category')}}" maxlength="191">
                            </div>
                            <input type="hidden" name="lang[]" value="default">
                                @foreach($language as $key=> $lang)
                                    <div class="form-group d-none lang_form" id="{{$lang}}-form">
                                        <label class="input-label" for="exampleFormControlInput1">{{translate('messages.name')}} ({{strtoupper($lang)}})</label>
                                        <input type="text" name="name[]"  value="{{ old('name.'.$key+1) }}" class="form-control" placeholder="{{translate('messages.new_category')}}" maxlength="191">
                                    </div>
                                    <input type="hidden" name="lang[]" value="{{$lang}}">
                                @endforeach
                            @else
                                <div class="form-group">
                                    <label class="input-label" for="exampleFormControlInput1">{{translate('messages.name')}}</label>
                                    <input type="text" name="name" class="form-control" placeholder="{{translate('messages.new_category')}}" value="{{old('name')}}" maxlength="191">
                                </div>
                                <input type="hidden" name="lang[]" value="default">
                            @endif
                            <input name="position" value="0" class="initial-hidden">
                        </div>
                        <div class="col-md-6">
                            <div class="h-100 d-flex align-items-center flex-column">
                                <label class="mb-3 text-center">{{translate('messages.image')}} <small class="text-danger">* ( {{translate('messages.ratio')}} 1:1)</small></label>
                                <label class="text-center my-auto position-relative d-inline-block">
                                    <img class="img--176 border" id="viewer"
                                        @if(isset($category))
                                        src="{{asset('storage/app/public/category')}}/{{$category['image']}}"
                                        @else
                                        src="{{asset('public/assets/admin/img/upload-img.png')}}"
                                        @endif
                                        alt="image"/>
                                    <div class="icon-file-group">
                                        <div class="icon-file">
                                            <input type="file" name="image" id="customFileEg1" class="custom-file-input read-url"
                                                accept=".jpg, .png, .jpeg, .gif, .bmp, .tif, .tiff|image/*" >
                                                <i class="tio-edit"></i>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="btn--container justify-content-end mt-3">
                        <button type="reset" id="reset_btn" class="btn btn--reset">{{translate('messages.reset')}}</button>
                        <button type="submit" class="btn btn--primary">{{isset($category)?translate('messages.update'):translate('messages.add')}}</button>
                    </div>

                </form>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header py-2 border-0">
                <div class="search--button-wrapper">
                    <h5 class="card-title">{{translate('messages.category_list')}}<span class="badge badge-soft-dark ml-2" id="itemCount">{{$categories->total()}}</span></h5>

                    <form class="search-form">
                        <!-- Search -->
                        <div class="input-group input--group">
                            <input type="search" name="search" value="{{ request()?->search ?? null }}" class="form-control min-height-45" placeholder="{{translate('messages.search_categories')}}" aria-label="{{translate('messages.ex_:_categories')}}">
                            <input type="hidden" name="position" value="0">
                            <button type="submit" class="btn btn--secondary min-height-45"><i class="tio-search"></i></button>
                        </div>
                        <!-- End Search -->
                    </form>
                    @if(request()->get('search'))
                    <button type="reset" class="btn btn--primary ml-2 location-reload-to-category" data-url="{{url()->full()}}">{{translate('messages.reset')}}</button>
                    @endif
                    <!-- Unfold -->
                    <div class="hs-unfold mr-2">
                        <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle min-height-40" href="javascript:;"
                            data-hs-unfold-options='{
                                    "target": "#usersExportDropdown",
                                    "type": "css-animation"
                                }'>
                            <i class="tio-download-to mr-1"></i> {{ translate('messages.export') }}
                        </a>

                        <div id="usersExportDropdown"
                            class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">

                            <span class="dropdown-header">{{ translate('messages.download_options') }}</span>
                            <a id="export-excel" class="dropdown-item" href="{{ route('admin.category.export-categories', ['type' => 'excel', request()->getQueryString()]) }}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                    src="{{ asset('public/assets/admin') }}/svg/components/excel.svg"
                                    alt="Image Description">
                                {{ translate('messages.excel') }}
                            </a>
                            <a id="export-csv" class="dropdown-item" href="{{ route('admin.category.export-categories', ['type' => 'csv', request()->getQueryString()]) }}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                    src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg"
                                    alt="Image Description">
                                .{{ translate('messages.csv') }}
                            </a>

                        </div>
                    </div>
                    <!-- End Unfold -->
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive datatable-custom">
                    <table id="columnSearchDatatable"
                        class="table table-borderless table-thead-bordered table-align-middle"
                        data-hs-datatables-options='{
                            "isResponsive": false,
                            "isShowPaging": false,
                            "paging":false,
                        }'>
                        <thead class="thead-light">
                            <tr>
                                <th class="border-0">{{translate('sl')}}</th>
                                <th class="border-0">{{translate('messages.id')}}</th>
                                <th class="border-0 w--1">{{translate('messages.name')}}</th>
                                <th class="border-0 text-center">{{translate('messages.status')}}</th>
                                <th class="border-0 text-center">{{translate('messages.featured')}}</th>
                                <th class="border-0 text-center">{{translate('messages.priority')}}</th>
                                <th class="border-0 text-center">{{translate('messages.action')}}</th>
                            </tr>
                        </thead>

                        <tbody id="table-div">
                        @foreach($categories as $key=>$category)
                            <tr>
                                <td>{{$key+$categories->firstItem()}}</td>
                                <td>{{$category->id}}</td>
                                <td>
                                    <span class="d-block font-size-sm text-body">
                                        {{Str::limit($category['name'], 20,'...')}}
                                    </span>
                                </td>
                                <td>
                                    <label class="toggle-switch toggle-switch-sm" for="stocksCheckbox{{$category->id}}">
                                        <input type="checkbox" data-url="{{route('admin.category.status',[$category['id'],$category->status?0:1])}}" class="toggle-switch-input redirect-url" id="stocksCheckbox{{$category->id}}" {{$category->status?'checked':''}}>
                                        <span class="toggle-switch-label mx-auto">
                                            <span class="toggle-switch-indicator"></span>
                                        </span>
                                    </label>
                                </td>
                                <td>
                                    <label class="toggle-switch toggle-switch-sm" for="featuredCheckbox{{$category->id}}">
                                        <input type="checkbox" data-url="{{route('admin.category.featured',[$category['id'],$category->featured?0:1])}}" class="toggle-switch-input redirect-url" id="featuredCheckbox{{$category->id}}" {{$category->featured?'checked':''}}>
                                        <span class="toggle-switch-label mx-auto">
                                            <span class="toggle-switch-indicator"></span>
                                        </span>
                                    </label>
                                </td>
                                <td>

                                    <form action="{{route('admin.category.priority',$category->id)}}" class="priority-form">
                                        <select name="priority" id="priority" class="form-control form--control-select  priority-select  mx-auto {{$category->priority == 0 ? 'text-title':''}} {{$category->priority == 1 ? 'text-info':''}} {{$category->priority == 2 ? 'text-success':''}}" >
                                            <option value="0" class="text--title" {{$category->priority == 0?'selected':''}}>{{translate('messages.normal')}}</option>
                                            <option value="1" class="text--title" {{$category->priority == 1?'selected':''}}>{{translate('messages.medium')}}</option>
                                            <option value="2" class="text--title" {{$category->priority == 2?'selected':''}}>{{translate('messages.high')}}</option>
                                        </select>
                                    </form>

                                </td>
                                <td>
                                    <div class="btn--container justify-content-center">
                                        <a class="btn action-btn btn--primary btn-outline-primary"
                                            href="{{route('admin.category.edit',[$category['id']])}}" title="{{translate('messages.edit_category')}}"><i class="tio-edit"></i>
                                        </a>
                                        <a class="btn action-btn btn--danger btn-outline-danger form-alert" href="javascript:"
                                        data-id="category-{{$category['id']}}" data-message="{{ translate('Want to delete this category') }}" title="{{translate('messages.delete_category')}}"><i class="tio-delete-outlined"></i>
                                        </a>
                                        <form action="{{route('admin.category.delete',[$category['id']])}}" method="post" id="category-{{$category['id']}}">
                                            @csrf @method('delete')
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @if(count($categories) !== 0)
            <hr>
            @endif
            <div class="page-area">
                {!! $categories->appends($_GET)->links() !!}
            </div>
            @if(count($categories) === 0)
            <div class="empty--data">
                <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="public">
                <h5>
                    {{translate('no_data_found')}}
                </h5>
            </div>
            @endif
        </div>

    </div>

@endsection

@push('script_2')
    <script src="{{asset('public/assets/admin')}}/js/view-pages/category-index.js"></script>
    <script>
    "use strict";
        $('.location-reload-to-category').on('click', function() {
            const url = $(this).data('url');
            let nurl = new URL(url);
            nurl.searchParams.delete('search');
            location.href = nurl;
        });

        $("#customFileEg1").change(function() {
            readURL(this);
            $('#viewer').show(1000)
        });
        $('#reset_btn').click(function(){
            $('#exampleFormControlSelect1').val(null).trigger('change');
                $('#viewer').attr('src', "{{asset('public/assets/admin/img/upload-img.png')}}");
        })
    </script>
@endpush
