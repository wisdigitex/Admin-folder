@extends('layouts.landing.app')
@section('title', translate('messages.store_registration'))
@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/toastr.css') }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/view-pages/vendor-registration.css') }}">
    <link rel="stylesheet" href="{{ asset('public/assets/landing/css/select2.min.css') }}"/>

    <style>
        .password-feedback {
    display: none;
    width: 100%;
    margin-top: .25rem;
    font-size: .875em;
    /* color: #35dc80; */
}
/* .password-feedback {
            font-size: 14px;
            margin-top: 5px;
        } */
        .valid {
            color: green;
        }
        .invalid {
            color: red;
        }
    </style>
@endpush
@section('content')
    <section class="m-0 py-5">
        <div class="container">
            <!-- Page Header -->
            <div class="section-header">
                <h2 class="title mb-2">{{ translate('messages.store') }} <span class="text--base">{{translate('application')}}</span></h2>
            </div>
            @php($language=\App\Models\BusinessSetting::where('key','language')->first())
            @php($language = $language->value ?? null)
            @php($defaultLang = 'en')
            <!-- End Page Header -->

            <!-- Stepper -->
                <div class="stepper">
                    <div id="show-step1" class="stepper-item active">
                        <div class="step-name">{{ translate('General Info') }}</div>
                    </div>
                    <div class="stepper-item" id="show-step2">
                        <div class="step-name">{{ translate('Business Plan') }}</div>
                    </div>
                    <div class="stepper-item">
                        <div class="step-name">{{ translate('Complete') }}</div>
                    </div>
                </div>
            <!-- Stepper -->


            <form class="reg-form js-validate" action="{{ route('restaurant.store') }}" method="post" enctype="multipart/form-data"
                id="form-id">
                @csrf




                <div id="reg-form-div">
                    <div class="card __card mb-3">
                        <div class="card-header">
                            <h5 class="card-title">
                                {{ translate('messages.store_info') }}
                            </h5>
                        </div>
                        <div class="card-body p-4">
                            @if($language)
                            <ul class="nav nav-tabs mb-4 store-apply-navs">
                                <li class="nav-item">
                                    <a class="nav-link lang_link active" href="#" id="default-link">{{ translate('Default') }}</a>
                                </li>
                                @foreach (json_decode($language) as $lang)
                                <li class="nav-item">
                                    <a class="nav-link lang_link" href="#" id="{{ $lang }}-link">{{
                                        \App\CentralLogics\Helpers::get_language_name($lang) . '(' . strtoupper($lang) . ')' }}</a>
                                </li>
                                @endforeach
                            </ul>
                            @endif
                            <div class="row g-4">
                                <div class="col-lg-6">
                                    @if ($language)
                                    <div class="lang_form" id="default-form">
                                        <div class="mb-4">
                                            <div class="form-group">
                                                <label class="input-label" for="default_name">{{ translate('messages.name') }}
                                                    ({{ translate('messages.Default') }})
                                                </label>
                                                <input type="text" name="name[]" value="{{ old('name.0') }}"  id="default_name" class="form-control __form-control"
                                                    placeholder="{{ translate('messages.store_name') }}" required>
                                            </div>
                                        </div>
                                        <input type="hidden" name="lang[]" value="default">
                                        <div class="mb-4">
                                            <div class="form-group mb-0">
                                                <label class="input-label" for="address">{{ translate('messages.address') }} ({{
                                                    translate('messages.default') }})</label>
                                                <textarea type="text" id="address" name="address[]"
                                                    placeholder="{{translate('Ex: ABC Company')}}"
                                                    class="form-control __form-control h-120">{{ old('address.0') }}</textarea>
                                            </div>
                                        </div>
                                    </div>
                                    @foreach (json_decode($language) as $key=>  $lang)
                                    <div class="d-none lang_form" id="{{ $lang }}-form">
                                        <div class="mb-4">
                                            <div class="form-group">
                                                <label class="input-label" for="{{ $lang }}_name">{{ translate('messages.name') }}
                                                    ({{ strtoupper($lang) }})
                                                </label>
                                                <input type="text" name="name[]"  value="{{ old('name.'.$key+1) }}" id="{{ $lang }}_name"
                                                    class="form-control __form-control"
                                                    placeholder="{{ translate('messages.store_name') }}">
                                            </div>
                                        </div>
                                        <input type="hidden" name="lang[]" value="{{ $lang }}">
                                        <div class="mb-4">
                                            <div class="form-group mb-0">
                                                <label class="input-label" for="address{{$lang}}">{{ translate('messages.address') }}
                                                    ({{
                                                    strtoupper($lang) }})</label>
                                                <textarea type="text" id="address{{$lang}}" name="address[]"
                                                    placeholder="{{translate('Ex: ABC Company')}}"
                                                    class="form-control __form-control h-120">  {{ old('address.'.$key+1) }}</textarea>
                                            </div>
                                        </div>
                                    </div>
                                    @endforeach
                                    @endif
                                    <div class="form-group mb-4">
                                        <label class="input-label" title="{{ translate('messages.select_zone_for_map') }}" for="choice_zones">{{ translate('messages.zone') }} <span
                                                class="form-label-secondary" data-toggle="tooltip" data-placement="right"
                                                data-original-title="{{ translate('messages.select_zone_for_map') }}"><img
                                                    src="{{ asset('/public/assets/admin/img/info-circle.svg') }}"
                                                    alt="{{ translate('messages.select_zone_for_map') }}"></span></label>
                                        <select name="zone_id" id="choice_zones" required
                                            class="form-control __form-control js-select2-custom js-example-basic-single"
                                            data-placeholder="{{ translate('messages.select_zone') }}">
                                            <option value="" selected disabled>{{ translate('messages.select_zone') }}</option>
                                            @foreach (\App\Models\Zone::active()->get() as $zone)
                                            @if (isset(auth('admin')->user()->zone_id))
                                            @if (auth('admin')->user()->zone_id == $zone->id)
                                            <option value="{{ $zone->id }}" selected>{{ $zone->name }}</option>
                                            @endif
                                            @else
                                            <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                                            @endif
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group mb-4">
                                        <label for="module_id" class="input-label">{{translate('messages.module')}} <small class="text-danger">({{translate('messages.Select_zone_first')}})</small></label>
                                        <select name="module_id" required id="module_id"
                                            class="js-data-example-ajax form-control __form-control"
                                            data-placeholder="{{translate('messages.select_module')}}">
                                        </select>
                                    </div>
                                    <div class="form-group mb-4">
                                        <label class="input-label" for="latitude">{{ translate('messages.latitude') }} <span
                                                class="input-label-secondary"
                                                title="{{ translate('messages.store_lat_lng_warning') }}"><img
                                                    src="{{ asset('/public/assets/admin/img/info-circle.svg') }}"
                                                    alt="{{ translate('messages.store_lat_lng_warning') }}"></span></label>
                                        <input type="text" id="latitude" name="latitude" class="form-control __form-control"
                                            placeholder="{{ translate('messages.Ex:') }} -94.22213" value="{{ old('latitude') }}"
                                            required readonly>
                                    </div>
                                    <div class="form-group">
                                        <label class="input-label" for="longitude">{{ translate('messages.longitude') }} <span
                                                class="input-label-secondary"
                                                title="{{ translate('messages.store_lat_lng_warning') }}"><img
                                                    src="{{ asset('/public/assets/admin/img/info-circle.svg') }}"
                                                    alt="{{ translate('messages.store_lat_lng_warning') }}"></span></label>
                                        <input type="text" name="longitude" class="form-control __form-control"
                                            placeholder="{{ translate('messages.Ex:') }} 103.344322" id="longitude"
                                            value="{{ old('longitude') }}" required readonly>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="mb-4">
                                        <div class="form-group">
                                            <label class="input-label" for="tax">{{ translate('messages.vat/tax') }} (%)</label>
                                            <input type="number" id="tax" name="tax" class="form-control __form-control"
                                                placeholder="{{ translate('messages.vat/tax') }}" min="0" step=".01" required
                                                value="{{ old('tax') }}">
                                        </div>
                                    </div>
                                    <div class="mb-4">
                                        <div class="form-group">
                                            <label class="input-label"
                                                for="minimum_delivery_time">{{translate('messages.approx_delivery_time')}}</label>
                                            <div class="input-group">
                                                <input type="number" id="minimum_delivery_time" name="minimum_delivery_time"
                                                    class="form-control __form-control" placeholder="Min: 10"
                                                    value="{{old('minimum_delivery_time')}}">
                                                <input type="number" name="maximum_delivery_time" id="max_delivery_time"
                                                    class="form-control __form-control" placeholder="Max: 20"
                                                    value="{{old('maximum_delivery_time')}}">
                                                <select name="delivery_time_type"
                                                    class="form-control __form-control text-capitalize" required>
                                                    <option value="min">{{translate('messages.minutes')}}</option>
                                                    <option value="hours">{{translate('messages.hours')}}</option>
                                                    <option value="days">{{translate('messages.days')}}</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="p-3 border border-success-light rounded mb-3">
                                        <input id="pac-input" class="controls rounded" style="height: 3em;width:fit-content;"
                                            title="{{translate('messages.search_your_location_here')}}" type="text"
                                            placeholder="{{translate('messages.search_here')}}" />
                                        <div class="h-255" id="map"></div>
                                    </div>
                                    <div class="d-flex gap-4">
                                        <div class="form-group w-140px flex-grow-1 d-flex flex-column justify-content-between">
                                            <label class="input-label pt-2">{{ translate('Upload Cover Photo') }}<small class="text-danger">
                                                * ({{ translate('messages.ratio') }} 2:1 )</small>
                                            </label>
                                            <label class="image--border position-relative">
                                                <img class="__register-img" id="coverImageViewer"
                                                    src="{{ asset('public/assets/admin/img/upload-img.png') }}" alt="Product thumbnail" />
                                                <div class="icon-file-group">
                                                    <div class="icon-file">
                                                        <input type="file" name="cover_photo" id="coverImageUpload"
                                                        class="form-control __form-control"
                                                        accept=".jpg, .png, .jpeg, .gif, .bmp, .tif, .tiff|image/*">
                                                        <img src="{{ asset('public/assets/admin/img/pen.png') }}" alt="">
                                                    </div>
                                                </div>
                                            </label>
                                        </div>
                                        <div class="form-group w-140px d-flex flex-column justify-content-between">
                                            <label class="input-label pt-2">{{ translate('messages.store_logo') }}<small class="text-danger">
                                                    * (
                                                    {{ translate('messages.ratio') }}
                                                    1:1
                                                    )</small></label>
                                            <label class="image--border position-relative img--100px">
                                                <img class="__register-img" id="logoImageViewer"
                                                    src="{{ asset('public/assets/admin/img/upload-img.png') }}" alt="Product thumbnail" />

                                                <div class="icon-file-group">
                                                    <div class="icon-file">
                                                        <input type="file" name="logo" id="customFileEg1" class="form-control __form-control"
                                                        accept=".jpg, .png, .jpeg, .gif, .bmp, .tif, .tiff|image/*" >
                                                        <img src="{{ asset('public/assets/admin/img/pen.png') }}" alt="">
                                                    </div>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <br>
                            <div class="card __card bg-F8F9FC mb-3">
                                <div class="card-header">
                                    <h5 class="card-title">
                                        {{ translate('messages.owner_info') }}
                                    </h5>
                                </div>
                                <div class="card-body p-4">
                                    <div class="row g-3">
                                        <div class="col-md-4 col-lg-4 col-sm-12">
                                            <div class="form-group">
                                                <label class="input-label" for="f_name">{{ translate('messages.first_name') }}</label>
                                                <input type="text" id="f_name" name="f_name" class="form-control __form-control"
                                                    placeholder="{{ translate('messages.first_name') }}" value="{{ old('f_name') }}"
                                                    required>
                                            </div>
                                        </div>
                                        <div class="col-md-4 col-lg-4 col-sm-12">
                                            <div class="form-group">
                                                <label class="input-label" for="l_name">{{ translate('messages.last_name') }}</label>
                                                <input type="text" id="l_name" name="l_name" class="form-control __form-control"
                                                    placeholder="{{ translate('messages.last_name') }}" value="{{ old('l_name') }}"
                                                    required>
                                            </div>
                                        </div>
                                        <div class="col-md-4 col-lg-4 col-sm-12">
                                            <div class="form-group">
                                                <label class="input-label" for="phone">{{ translate('messages.phone') }}</label>
                                                <input type="tel" id="phone" name="phone" class="form-control __form-control"
                                                    placeholder="{{ translate('messages.Ex:') }} 017********" value="{{ old('phone') }}"
                                                    required>
                                            </div>


                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card __card bg-F8F9FC mb-3">
                                <div class="card-header">
                                    <h5 class="card-title">
                                        {{ translate('messages.login_info') }}
                                    </h5>
                                </div>
                                <div class="card-body p-4">
                                    <div class="row g-3">
                                        <div class="col-md-4 col-sm-12 col-lg-4">
                                            <div class="form-group">
                                                <label class="input-label" for="email">{{ translate('messages.email') }}</label>
                                                <input type="email" id="email" name="email" class="form-control __form-control"
                                                    placeholder="{{ translate('messages.Ex:') }} ex@example.com" value="{{ old('email') }}"

                                                    required>
                                            </div>
                                        </div>
                                        <div class="col-md-4 col-sm-12 col-lg-4">
                                            <div class="form-group">
                                                <label class="input-label" title="{{ translate('messages.Must_contain_at_least_one_number_and_one_uppercase_and_lowercase_letter_and_symbol,_and_at_least_8_or_more_characters') }}" for="exampleInputPassword">{{ translate('messages.password') }} &nbsp;
                                                    <span
                                            class="form-label-secondary" data-toggle="tooltip" data-placement="right"
                                            data-original-title="{{ translate('messages.Must_contain_at_least_one_number_and_one_uppercase_and_lowercase_letter_and_symbol,_and_at_least_8_or_more_characters') }}"><img
                                                src="{{ asset('/public/assets/admin/img/info-circle.svg') }}"
                                                alt="{{ translate('messages.Must_contain_at_least_one_number_and_one_uppercase_and_lowercase_letter_and_symbol,_and_at_least_8_or_more_characters') }}"></span>

                                                </label>
                                                <label class="position-relative m-0 d-block">
                                                    <input type="password" name="password"
                                                        placeholder="{{ translate('messages.password_length_placeholder', ['length' => '8+']) }}"
                                                        class="form-control __form-control form-control __form-control-user" minlength="6"
                                                        id="exampleInputPassword" required value="{{ old('password') }}" >
                                                        <span class="show-password">
                                                            <span class="icon-2">
                                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                                                </svg>
                                                            </span>
                                                            <span class="icon-1">
                                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                                                                </svg>
                                                            </span>
                                                        </span>
                                                        </label>
                                                    <div id="password-feedback" class="pass password-feedback">{{ translate('messages.password_not_matched') }}</div>
                                            </div>
                                        </div>
                                        <div class="col-md-4 col-sm-12 col-lg-4">
                                            <div class="form-group">
                                                <label class="input-label" for="exampleRepeatPassword">{{
                                                    translate('messages.confirm_password')
                                                    }}</label>
                                                <label class="position-relative m-0 d-block">
                                                    <input type="password" name="confirm-password"
                                                        class="form-control __form-control form-control __form-control-user" minlength="6"
                                                        id="exampleRepeatPassword"
                                                        placeholder="{{ translate('messages.password_length_placeholder', ['length' => '8+']) }}"
                                                        required value="{{ old('confirm-password') }}">
                                                        <span class="show-password">
                                                            <span class="icon-2">
                                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                                                </svg>
                                                            </span>
                                                            <span class="icon-1">
                                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                                                                </svg>
                                                            </span>
                                                        </span>
                                                </label>
                                                <div class="pass invalid-feedback">{{ translate('messages.password_not_matched') }}</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row mt-5">
                                        <div class="col-md-6 col-lg-4">
                                            @php($recaptcha = \App\CentralLogics\Helpers::get_business_settings('recaptcha'))
                                            @if(isset($recaptcha) && $recaptcha['status'] == 1)
                                            <div id="recaptcha_element" class="w-100" data-type="image"></div>
                                            <br />
                                            @else
                                            <div class="row g-3">
                                                <div class="col-6">
                                                    <input type="text" class="form-control" name="custome_recaptcha"
                                                        id="custome_recaptcha" required
                                                        placeholder="{{translate('Enter recaptcha value')}}" autocomplete="off"
                                                        value="{{env('APP_DEBUG')?session('six_captcha'):''}}">
                                                </div>
                                                <div class="col-6 recap-img-div">
                                                    <img src="{!!  $custome_recaptcha->inline() ?? '' !!}" alt="image"
                                                        class="recap-img" />
                                                </div>
                                            </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="text-end pt-4 d-flex flex-wrap justify-content-end gap-3">
                                <button type="reset" id='reset-btn' class="cmn--btn btn--secondary shadow-none rounded-md border-0 outline-0">{{ translate('Reset')}}</button>
                                <button  type="{{  \App\CentralLogics\Helpers::subscription_check() == 1  ? 'button' : 'submit' }}" id="show-business-plan-div" class="cmn--btn rounded-md border-0 outline-0">{{ translate('Next') }}</button>
                            </div>
                        </div>
                    </div>

                </div>

                    @if (\App\CentralLogics\Helpers::subscription_check() )
                    <div class="d-none" id="business-plan-div">
                        <div class="card __card mb-3">
                            <div class="card-header border-0">
                                <h5 class="card-title text-center">
                                    {{ translate('Choose Your Business Plan') }}
                                </h5>
                            </div>
                            <div class="card-body p-4">
                                <div class="row">
                                    @if ( \App\CentralLogics\Helpers::commission_check())

                                    <div class="col-sm-6">
                                        <label class="plan-check-item">
                                            <input type="radio" name="business_plan" value="commission-base" class="d-none" checked>
                                            <div class="plan-check-item-inner">
                                                <h5>{{ translate('Commision_Base') }}</h5>
                                                <p>
                                                    {{ translate('Store will pay') }} {{ $admin_commission }}% {{ translate('commission to') }} {{ $business_name }} {{ translate('from each order. You will get access of all the features and options  in store panel , app and interaction with user.') }}
                                                </p>
                                            </div>
                                        </label>
                                    </div>
                                    @endif
                                    <div class="col-sm-6">
                                        <label class="plan-check-item">
                                            <input type="radio" name="business_plan"  value="subscription-base" class="d-none" >
                                            <div class="plan-check-item-inner">
                                                <h5>{{ translate('Subscription Base') }}</h5>
                                                <p>
                                                {{ translate('Run store by puchasing subsciption packages. You will have access the features of in store panel , app and interaction with user according to the subscription packages.') }}
                                                </p>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                                <div id="subscription-plan">
                                    <br>
                                    <div class="card-header px-0 m-0 border-0">
                                        <h5 class="card-title text-center">
                                            {{ translate('Choose Subscription Package') }}
                                        </h5>
                                    </div>
                                    <div class="plan-slider owl-theme owl-carousel owl-refresh">

                                        @forelse ($packages as $key=> $package)
                                        <label class="__plan-item {{ count($packages) > 4 &&  $key == 2 ||( count($packages) < 5 &&  $key == 1) ? 'active' : '' }} ">
                                            <input type="radio" name="package_id" id="package_id" value="{{ $package->id }}"  class="d-none">
                                            <div class="inner-div">
                                                <div class="text-center">

                                                    <h3 class="title">{{ $package->package_name }}</h3>
                                                    <h2 class="price">{{ \App\CentralLogics\Helpers::format_currency($package->price)}}</h2>
                                                    <div class="day-count">{{ $package->validity }} {{ translate('messages.days') }}</div>
                                                </div>
                                                <ul class="info">

                                                @if ($package->pos)
                                                <li>
                                                    <img src="{{asset('/public/assets/landing/img/check-1.svg')}}" class="check" alt="">
                                                        <img src="{{asset('/public/assets/landing/img/check-2.svg')}}" class="check-white" alt=""> <span>  {{ translate('messages.POS') }} </span>
                                                </li>
                                                @endif
                                                @if ($package->mobile_app)
                                                <li>
                                                    <img src="{{asset('/public/assets/landing/img/check-1.svg')}}" class="check" alt="">
                                                        <img src="{{asset('/public/assets/landing/img/check-2.svg')}}" class="check-white" alt=""> <span>  {{ translate('messages.mobile_app') }} </span>
                                                </li>
                                                @endif
                                                @if ($package->chat)
                                                <li>
                                                    <img src="{{asset('/public/assets/landing/img/check-1.svg')}}" class="check" alt="">
                                                        <img src="{{asset('/public/assets/landing/img/check-2.svg')}}" class="check-white" alt=""> <span>  {{ translate('messages.chatting_options') }} </span>
                                                </li>
                                                @endif
                                                @if ($package->review)
                                                <li>
                                                    <img src="{{asset('/public/assets/landing/img/check-1.svg')}}" class="check" alt="">
                                                        <img src="{{asset('/public/assets/landing/img/check-2.svg')}}" class="check-white" alt=""> <span>  {{ translate('messages.review_section') }} </span>
                                                </li>
                                                @endif
                                                @if ($package->self_delivery)
                                                <li>
                                                    <img src="{{asset('/public/assets/landing/img/check-1.svg')}}" class="check" alt="">
                                                        <img src="{{asset('/public/assets/landing/img/check-2.svg')}}" class="check-white" alt=""> <span>  {{ translate('messages.self_delivery') }} </span>
                                                </li>
                                                @endif
                                                @if ($package->max_order == 'unlimited')
                                                <li>
                                                    <img src="{{asset('/public/assets/landing/img/check-1.svg')}}" class="check" alt="">
                                                        <img src="{{asset('/public/assets/landing/img/check-2.svg')}}" class="check-white" alt=""> <span>  {{ translate('messages.Unlimited_Orders') }} </span>
                                                </li>
                                                @else
                                                <li>
                                                    <img src="{{asset('/public/assets/landing/img/check-1.svg')}}" class="check" alt="">
                                                        <img src="{{asset('/public/assets/landing/img/check-2.svg')}}" class="check-white" alt=""> <span>  {{ $package->max_order }} {{ translate('messages.Orders') }} </span>
                                                </li>
                                                @endif
                                                @if ($package->max_product == 'unlimited')
                                                <li>
                                                    <img src="{{asset('/public/assets/landing/img/check-1.svg')}}" class="check" alt="">
                                                        <img src="{{asset('/public/assets/landing/img/check-2.svg')}}" class="check-white" alt=""> <span>  {{ translate('messages.Unlimited_uploads') }} </span>
                                                </li>
                                                @else
                                                <li>
                                                    <img src="{{asset('/public/assets/landing/img/check-1.svg')}}" class="check" alt="">
                                                        <img src="{{asset('/public/assets/landing/img/check-2.svg')}}" class="check-white" alt=""> <span>  {{ $package->max_product }} {{ translate('messages.uploads') }} </span>
                                                </li>
                                                @endif
                                                </ul>
                                            </div>
                                        </label>

                                        @empty

                                        @endforelse

                                    </div>
                                </div>
                                <div class="text-end pt-5 d-flex flex-wrap justify-content-end gap-3">
                                    <button type="button" id="back-to-form" class="cmn--btn btn--secondary shadow-none rounded-md border-0 outline-0">{{ translate('Back')}}</button>
                                    <button type="submit" class="cmn--btn rounded-md border-0 outline-0">{{ translate('Next')}}</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    @endif

            </form>
        </div>
    </section>

    @endsection
    @push('script_2')

        <script src="{{ asset('public/assets/admin/js/spartan-multi-image-picker.js') }}"></script>
        <script src="https://polyfill.io/v3/polyfill.min.js?features=default"></script>
        <script
            src="https://maps.gomaps.pro/maps/api/js?key={{ \App\Models\BusinessSetting::where('key', 'map_api_key')->first()->value }}&libraries=drawing,places&v=3.45.8">
        </script>
        <script type="text/javascript">
            "use strict";

            @php($default_location = \App\Models\BusinessSetting::where('key', 'default_location')->first())
            @php($default_location = $default_location->value ? json_decode($default_location->value, true) : 0)
            let myLatlng = {
                lat: {{ $default_location ? $default_location['lat'] : '23.757989' }},
                lng: {{ $default_location ? $default_location['lng'] : '90.360587' }}
            };
            let map = new google.maps.Map(document.getElementById("map"), {
                zoom: 13,
                center: myLatlng,
            });
           let zonePolygon = null;
            let infoWindow = new google.maps.InfoWindow({
                content: "Click the map to get Lat/Lng!",
                position: myLatlng,
            });
           let bounds = new google.maps.LatLngBounds();

            $('#choice_zones').on('change', function() {
               let id = $(this).val();
                $.get({
                    url: '{{ url('/') }}/admin/zone/get-coordinates/' + id,
                    dataType: 'json',
                    success: function(data) {
                        if (zonePolygon) {
                            zonePolygon.setMap(null);
                        }
                        zonePolygon = new google.maps.Polygon({
                            paths: data.coordinates,
                            strokeColor: "#FF0000",
                            strokeOpacity: 0.8,
                            strokeWeight: 2,
                            fillColor: 'white',
                            fillOpacity: 0,
                        });
                        zonePolygon.setMap(map);
                        zonePolygon.getPaths().forEach(function(path) {
                            path.forEach(function(latlng) {
                                bounds.extend(latlng);
                                map.fitBounds(bounds);
                            });
                        });
                        map.setCenter(data.center);
                        google.maps.event.addListener(zonePolygon, 'click', function(mapsMouseEvent) {
                            infoWindow.close();
                            // Create a new InfoWindow.
                            infoWindow = new google.maps.InfoWindow({
                                position: mapsMouseEvent.latLng,
                                content: JSON.stringify(mapsMouseEvent.latLng.toJSON(),
                                    null, 2),
                            });
                            let coordinates;
                            coordinates = JSON.stringify(mapsMouseEvent.latLng.toJSON(), null,
                                2);
                            coordinates = JSON.parse(coordinates);

                            document.getElementById('latitude').value = coordinates['lat'];
                            document.getElementById('longitude').value = coordinates['lng'];
                            infoWindow.open(map);
                        });
                    },
                });
            });

            $(document).ready(function() {
                $('#module_id').select2({
                    ajax: {
                        url: '{{url('/')}}/store/get-all-modules/',
                        data: function (params) {
                            return {
                                q: params.term, // search term
                                page: params.page,
                                zone_id: zone_id
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
            });

        </script>
        <script src="{{ asset('public/assets/admin/js/view-pages/vendor-registration.js') }}"></script>
            @if(isset($recaptcha) && $recaptcha['status'] == 1)

                <script type="text/javascript">
                "use strict";
                    let onloadCallback = function () {
                        grecaptcha.render('recaptcha_element', {
                            'sitekey': '{{ \App\CentralLogics\Helpers::get_business_settings('recaptcha')['site_key'] }}'
                        });
                    };
                </script>
                <script src="https://www.google.com/recaptcha/api.js?onload=onloadCallback&render=explicit" async defer></script>
                <script>
                    "use strict";
                    $("#form-id").on('submit',function(e) {
                        let response = grecaptcha.getResponse();

                        if (response.length === 0) {
                            e.preventDefault();
                            toastr.error("{{translate('messages.Please check the recaptcha')}}");
                        }
                    });
                </script>
            @endif


<script>
        $(document).on('keyup', 'input[name="password"]', function () {
            const password = $(this).val();
            const feedback = $('#password-feedback');

            const minLength = password.length >= 8;
            const hasLowerCase = /[a-z]/.test(password);
            const hasUpperCase = /[A-Z]/.test(password);
            const hasNumber = /[0-9]/.test(password);
            const hasSymbol = /[!@#$%^&*(),.?":{}|<>]/.test(password);

            if (minLength && hasLowerCase && hasUpperCase && hasNumber && hasSymbol) {
                feedback.text("{{ translate('Password is valid') }}");
                feedback.removeClass('invalid').addClass('valid');
            } else {
                feedback.text("{{ translate('Password format is invalid') }}");
                feedback.removeClass('valid').addClass('invalid');
            }
        });



            $('#show-business-plan-div').on('click', function(e){
                const fileInputs = document.querySelectorAll('input[type="file"]');
                fileInputs.forEach(input => {

                    if (input.files.length === 0) {
                        toastr.error("{{translate('Store_logo_&_cover_photos_are_required')}}");
                        e.preventDefault();
                    }
                        else if($('#default_name').val().length === 0 ){
                        toastr.error("{{translate('Store_name_is_required')}}");
                        e.preventDefault();
                    }
                        else if($('#address').val().length === 0 ){
                        toastr.error("{{translate('Store_address_is_required')}}");
                        e.preventDefault();
                    }
                        else if($('#address').val().length === 0 ){
                        toastr.error("{{translate('Store_address_is_required')}}");
                        e.preventDefault();
                    }
                        else if(!$('#choice_zones').val()){
                        toastr.error("{{translate('You_must_select_a_zone')}}");
                        e.preventDefault();
                    }
                        else if(!$('#module_id').val()){
                        toastr.error("{{translate('You_must_select_a_module')}}");
                        e.preventDefault();
                    }
                        else if($('#latitude').val().length === 0 ){
                        toastr.error("{{translate('Must_click_on_the_map_for_lat/long')}}");
                        e.preventDefault();
                    }
                        else if($('#longitude').val().length === 0 ){
                        toastr.error("{{translate('Must_click_on_the_map_for_lat/long')}}");
                        e.preventDefault();
                    }
                        else if($('#tax').val().length === 0 ){
                        toastr.error("{{translate('tax_is_required')}}");
                        e.preventDefault();
                    }
                        else if($('#minimum_delivery_time').val().length === 0 ){
                        toastr.error("{{translate('minimum_delivery_time_is_required')}}");
                        e.preventDefault();
                    }
                        else if($('#max_delivery_time').val().length === 0 ){
                        toastr.error("{{translate('max_delivery_time_is_required')}}");
                        e.preventDefault();
                    }
                        else if($('#f_name').val().length === 0 ){
                        toastr.error("{{translate('first_name_is_required')}}");
                        e.preventDefault();
                    }
                        else if($('#l_name').val().length === 0 ){
                        toastr.error("{{translate('last_name_is_required')}}");
                        e.preventDefault();
                    }
                        else if($('#phone').val().length < 5 ){
                        toastr.error("{{translate('valid_phone_number_is_required')}}");
                        e.preventDefault();
                    }
                        else if($('#email').val().length === 0 ){
                        toastr.error("{{translate('email_is_required')}}");
                        e.preventDefault();
                    }
                        else if($('#exampleInputPassword').val().length === 0 ){
                        toastr.error("{{translate('password_is_required')}}");
                        e.preventDefault();
                    }
                        else if($('#exampleRepeatPassword').val() !== $('#exampleInputPassword').val() ){
                        toastr.error("{{translate('confirm_password_does_not_match')}}");
                        e.preventDefault();
                    }

                    else{
                        $('#business-plan-div').removeClass('d-none');
                        $('#reg-form-div').addClass('d-none');
                        $('#show-step2').addClass('active');
                        $('#show-step1').removeClass('active');
                        $(window).scrollTop(0);
                    }
                });
            })

            $('#back-to-form').on('click', function(){
                $('#business-plan-div').addClass('d-none');
                $('#reg-form-div').removeClass('d-none');
                $('#show-step1').addClass('active');
                $('#show-step2').removeClass('active');
                $(window).scrollTop(0);
            })
            $("#form-id").on('submit',function(e) {


                const radios = document.querySelectorAll('input[name="business_plan"]');
                let selectedValue = null;


                    for (const radio of radios) {
                        if (radio.checked) {
                            selectedValue = radio.value;
                            break;
                        }
                    }


                    if( selectedValue === 'subscription-base'  ){
                        const package_radios = document.querySelectorAll('input[name="package_id"]');
                        let selectedpValue = null;
                            for (const pradio of package_radios) {
                                if (pradio.checked) {
                                    selectedpValue = pradio.value;
                                    break;
                                }
                            }

                        if(!selectedpValue){
                            toastr.error("{{translate('You_must_select_a_package')}}");
                        e.preventDefault();
                        }
                    }





                    });




</script>
    <script src="{{ asset('public/assets/landing/js/select2.min.js') }}"></script>
    <script>

        $('.plan-slider').owlCarousel({
            loop: false,
            margin: 30,
            responsiveClass:true,
            nav:false,
            dots:false,
            items: 3,
            // center: true,
            // autoplay:true,
            // autoplayTimeout:2500,
            // autoplayHoverPause:true,
            startPosition: 1,

            responsive:{
                0: {
                    items:1.1,
                    margin: 10,
                },
                375: {
                    items:1.3,
                    margin: 30,
                },
                576: {
                    items:1.7,
                },
                768: {
                    items:2.2,
                    margin: 40,
                },
                992: {
                    items: 3,
                    margin: 40,
                },
                1200: {
                    items: 4,
                    margin: 40,
                }
            }
        })
    </script>

    <script>
        $(window).on('load', function(){
            $('input[name="business_plan"]').each(function(){
                if($(this).is(':checked')){
                    if($(this).val() == 'subscription-base'){
                        $('#subscription-plan').show()
                    }else {
                        $('#subscription-plan').hide()
                    }
                }
            })
            $('input[name="package_id"]').each(function(){
                if($(this).is(':checked')){
                    $(this).closest('.__plan-item').addClass('active')
                }
            })
        })
        $('input[name="business_plan"]').on('change', function(){
            if($(this).val() == 'subscription-base'){
                $('#subscription-plan').slideDown()
            }else {
                $('#subscription-plan').slideUp()
            }
        })
        $('input[name="package_id"]').on('change', function(){
            $('input[name="package_id"]').each(function(){
                $(this).closest('.__plan-item').removeClass('active')
            })
            $(this).closest('.__plan-item').addClass('active')
        })
        $('#reset-btn').on('click', function(){
            location.reload()
        })
    </script>

    @endpush
