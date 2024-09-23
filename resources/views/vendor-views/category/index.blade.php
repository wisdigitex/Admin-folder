@extends('layouts.vendor.app')

@section('title',translate('messages.category'))

@push('css_or_js')

@endpush

@section('content')
    <div class="content container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/categories.png')}}" class="w--20" alt="">
                </span>
                <span>
                    {{translate('messages.category_list')}} <span class="badge badge-soft-dark ml-2" id="itemCount">{{$categories->total()}}</span>
                </span>
            </h1>
        </div>
        <!-- End Page Header -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header py-2 border-0">
                        <div class="search--button-wrapper justify-content-end">
                            <form  class="search-form">

                                <!-- Search -->
                                <div class="input-group input--group">
                                    <input type="search"  value="{{ request()?->search ?? null }}" name="search" class="form-control min-height-45" placeholder="{{translate('messages.search_categories')}}" aria-label="{{translate('messages.ex_:_categories')}}">
                                    <button type="submit" class="btn btn--secondary min-height-45"><i class="tio-search"></i></button>
                                </div>
                                <!-- End Search -->
                            </form>
                            <!-- Unfold -->
                            <div class="hs-unfold mr-2">
                                <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle h--40px" href="javascript:"
                                    data-hs-unfold-options='{
                                        "target": "#usersExportDropdown",
                                        "type": "css-animation"
                                    }'>
                                    <i class="tio-download-to mr-1"></i> {{translate('messages.export')}}
                                </a>

                                <div id="usersExportDropdown"
                                        class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">

                                    <span
                                        class="dropdown-header">{{translate('messages.download_options')}}</span>
                                    <a id="export-excel" class="dropdown-item" href="{{route('vendor.category.export-categories', ['type'=>'excel',request()->getQueryString()])}}">
                                        <img class="avatar avatar-xss avatar-4by3 mr-2"
                                                src="{{asset('public/assets/admin/svg/components/excel.svg')}}"
                                                alt="Image Description">
                                        {{translate('messages.excel')}}
                                    </a>
                                    <a id="export-csv" class="dropdown-item" href="{{route('vendor.category.export-categories', ['type'=>'csv',request()->getQueryString()])}}">
                                        <img class="avatar avatar-xss avatar-4by3 mr-2"
                                                src="{{asset('public/assets/admin/svg/components/placeholder-csv-format.svg')}}"
                                                alt="Image Description">
                                        .{{translate('messages.csv')}}
                                    </a>

                                </div>
                            </div>
                            <!-- End Unfold -->
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive datatable-custom">
                            <table id="columnSearchDatatable"
                                class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table"
                                data-hs-datatables-options='{
                                    "search": "#datatableSearch",
                                    "entries": "#datatableEntries",
                                    "isResponsive": false,
                                    "isShowPaging": false,
                                    "paging":false,
                                }'>
                                <thead class="thead-light">
                                    <tr>
                                        <th class="w-33p border-0 text-center">{{translate('messages.#')}}</th>
                                        <th class="w-33p border-0 text-center">{{translate('messages.category_id')}}</th>
                                        <th class="w-33p border-0 text-center">{{translate('messages.category_name')}}</th>
                                    </tr>
                                </thead>

                                <tbody id="table-div">
                                @foreach($categories as $key=>$category)
                                    <tr>
                                        <td class="text-center">{{$key+$categories->firstItem()}}</td>
                                        <td class="text-center">{{$category->id}}</td>
                                        <td class="text-center">
                                        <span class="d-block font-size-sm text-body">
                                            {{Str::limit($category['name'],20,'...')}}
                                        </span>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer page-area">
                        <!-- Pagination -->
                        {!! $categories->links() !!}
                        <!-- Pagination -->
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
            </div>
        </div>
    </div>

@endsection

