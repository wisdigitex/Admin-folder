<div id="sidebarMain" class="d-none">
    <aside
        class="js-navbar-vertical-aside navbar navbar-vertical-aside navbar-vertical navbar-vertical-fixed navbar-expand-xl navbar-bordered  ">
        <div class="navbar-vertical-container">
            <div class="navbar-brand-wrapper justify-content-between">
                <!-- Logo -->
                @php($store_logo = \App\Models\BusinessSetting::where(['key' => 'logo'])->first())
                <a class="navbar-brand" href="{{ route('admin.dispatch.dashboard') }}" aria-label="Front">
                    <img class="navbar-brand-logo initial--36 onerror-image onerror-image"
                         data-onerror-image="{{ asset('public/assets/admin/img/160x160/img2.jpg') }}"
                         src="{{\App\CentralLogics\Helpers::get_full_url('business', $store_logo?->value?? '', $store_logo?->storage[0]?->value ?? 'public','favicon')}}"
                         alt="Logo">
                    <img class="navbar-brand-logo-mini initial--36 onerror-image onerror-image"
                         data-onerror-image="{{ asset('public/assets/admin/img/160x160/img2.jpg') }}"
                         src="{{\App\CentralLogics\Helpers::get_full_url('business', $store_logo?->value?? '', $store_logo?->storage[0]?->value ?? 'public','favicon')}}"
                         alt="Logo">
                </a>
                <!-- End Logo -->

                <!-- Navbar Vertical Toggle -->
                <button type="button"
                        class="js-navbar-vertical-aside-toggle-invoker navbar-vertical-aside-toggle btn btn-icon btn-xs btn-ghost-dark">
                    <i class="tio-clear tio-lg"></i>
                </button>
                <!-- End Navbar Vertical Toggle -->

                <div class="navbar-nav-wrap-content-left">
                    <!-- Navbar Vertical Toggle -->
                    <button type="button" class="js-navbar-vertical-aside-toggle-invoker close">
                        <i class="tio-first-page navbar-vertical-aside-toggle-short-align" data-toggle="tooltip"
                           data-placement="right" title="Collapse"></i>
                        <i class="tio-last-page navbar-vertical-aside-toggle-full-align"
                           data-template='<div class="tooltip d-none d-sm-block" role="tooltip"><div class="arrow"></div><div class="tooltip-inner"></div></div>'></i>
                    </button>
                    <!-- End Navbar Vertical Toggle -->
                </div>

            </div>

            <!-- Content -->
            <div class="navbar-vertical-content bg--005555" id="navbar-vertical-content">
                <form autocomplete="off" class="sidebar--search-form">
                    <div class="search--form-group">
                        <button type="button" class="btn"><i class="tio-search"></i></button>
                        <input autocomplete="false" name="qq" type="text" class="form-control form--control"
                               placeholder="{{ translate('Search Menu...') }}" id="search">
                        <div id="search-suggestions" class="flex-wrap mt-1"></div>
                    </div>
                </form>
                <ul class="navbar-nav navbar-nav-lg nav-tabs">

                    <!-- Business Settings -->
                    <li class="nav-item">
                        <small class="nav-subtitle"
                               title="{{ translate('messages.business_settings') }}">{{ translate('messages.business_management') }}</small>
                        <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                    </li>


                    @if (\App\CentralLogics\Helpers::module_permission_check('zone'))
                        <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/zone*') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link"
                               href="{{ route('admin.business-settings.zone.home') }}"
                               title="{{ translate('messages.zone_setup') }}">
                                <i class="tio-city nav-icon"></i>
                                <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                            {{ translate('messages.zone_setup') }} </span>
                            </a>
                        </li>
                    @endif

                    @if (\App\CentralLogics\Helpers::module_permission_check('module'))
                        <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/module') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" id="tourb-3"
                               href="javascript:" title="{{ translate('messages.system_module_setup') }}">
                                <i class="tio-globe nav-icon"></i>
                                <span class="text-truncate">{{ translate('messages.module_setup') }}</span>
                            </a>
                            <ul class="js-navbar-vertical-aside-submenu nav nav-sub"
                                style="display:{{ Request::is('admin/business-settings/module*') ? 'block' : 'none' }}">
                                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/module/store') ? 'active' : '' }}">
                                    <a class="js-navbar-vertical-aside-menu-link nav-link"
                                       href="{{ route('admin.business-settings.module.create') }}"
                                       title="{{ translate('messages.add_Business_Module') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate">
                                    {{ translate('messages.add_Business_Module') }}
                                </span>
                                    </a>
                                </li>
                                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/module*') ? 'active' : '' }}">
                                    <a class="js-navbar-vertical-aside-menu-link nav-link"
                                       href="{{ route('admin.business-settings.module.index') }}"
                                       title="{{ translate('messages.modules') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate">
                                    {{ translate('messages.modules') }}
                                </span>
                                    </a>
                                </li>
                            </ul>
                        </li>
                    @endif
                    @if (\App\CentralLogics\Helpers::module_permission_check('settings'))
                        <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/business-setup*') || Request::is('admin/business-settings/language*') ? 'active' : '' }}">
                            <a class="nav-link " href="{{ route('admin.business-settings.business-setup') }}"
                               title="{{ translate('messages.business_setup') }}">
                                <span class="tio-settings nav-icon"></span>
                                <span class="text-truncate">{{ translate('messages.business_settings') }}</span>
                            </a>
                        </li>
                    @endif
{{--                    @if (\App\CentralLogics\Helpers::module_permission_check('settings') && (auth('admin')->user()->role_id == 1))--}}
{{--                        <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/external-system*') ? 'active' : '' }}">--}}
{{--                            <a class="nav-link " href="{{ route('admin.business-settings.external-system.drivemond-configuration') }}"--}}
{{--                               title="{{ translate('messages.Ride_Share_Setup_&_Integration') }}">--}}
{{--                                <span class="tio-settings-vs-outlined nav-icon"></span>--}}
{{--                                <span class="text-truncate">{{ translate('messages.Ride_Share_Setup_&_Integration') }}</span>--}}
{{--                            </a>--}}
{{--                        </li>--}}
{{--                    @endif--}}

                    @if (\App\CentralLogics\Helpers::module_permission_check('subscription'))
                        <li class="navbar-vertical-aside-has-menu @yield('subscription')">
                            <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" id="tourb-3"
                               href="javascript:" title="{{ translate('messages.subscription_management') }}">
                                <i class="tio-crown nav-icon"></i>
                                <span class="text-truncate">{{ translate('messages.subscription_management') }}</span>
                            </a>
                            <ul class="js-navbar-vertical-aside-submenu nav nav-sub"
                                style="display:{{ Request::is('admin/business-settings/subscription*') ? 'block' : 'none' }}">
                                <li class="navbar-vertical-aside-has-menu @yield('subscription_index')">
                                    <a class="js-navbar-vertical-aside-menu-link nav-link"
                                       href="{{  route('admin.business-settings.subscriptionackage.index') }}"
                                       title="{{ translate('messages.subscription_Package') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate">
                                    {{ translate('messages.subscription_Package') }}
                                </span>
                                    </a>
                                </li>
                                <li class="navbar-vertical-aside-has-menu  @yield('subscriberList')">
                                    <a class="js-navbar-vertical-aside-menu-link nav-link"
                                       href="{{ route('admin.business-settings.subscriptionackage.subscriberList') }}"
                                       title="{{ translate('messages.Subscriber_List') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate">
                                    {{ translate('messages.Subscriber_List') }}
                                </span>
                                    </a>
                                </li>
                                <li class="navbar-vertical-aside-has-menu  @yield('subscription_settings')">
                                    <a class="js-navbar-vertical-aside-menu-link nav-link"
                                       href="{{ route('admin.business-settings.subscriptionackage.settings') }}"
                                       title="{{ translate('messages.settings') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate">
                                    {{ translate('messages.settings') }}
                                </span>
                                    </a>
                                </li>
                            </ul>
                        </li>
                    @endif

                    @if (\App\CentralLogics\Helpers::module_permission_check('settings'))

                        <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/pages*') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:"
                               title="{{ translate('messages.pages_setup') }}">
                                <i class="tio-pages nav-icon"></i>
                                <span
                                    class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('messages.pages_&_social_media') }}</span>
                            </a>
                            <ul class="js-navbar-vertical-aside-submenu nav nav-sub"
                                style="display:{{ Request::is('admin/business-settings/pages*') ? 'block' : 'none' }}">

                                <li class="navbar-vertical-aside-has-menu {{Request::is('admin/business-settings/pages/social-media')?'active':''}}">
                                    <a class="nav-link " href="{{route('admin.business-settings.social-media.index')}}"
                                       title="{{translate('messages.Social Media')}}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate">{{translate('messages.Social Media')}}</span>
                                    </a>
                                </li>

                                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/pages/admin-landing-page-settings*') ? 'active' : '' }}">
                                    <a class="nav-link "
                                       href="{{ route('admin.business-settings.admin-landing-page-settings','fixed-data') }}"
                                       title="{{ translate('messages.admin_landing_page_settings') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span
                                            class="text-truncate">{{ translate('messages.admin_landing_page') }}</span>
                                    </a>
                                </li>
                                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/pages/react-landing-page-settings*') ? 'active' : '' }}">
                                    <a class="nav-link "
                                       href="{{ route('admin.business-settings.react-landing-page-settings','header') }}"
                                       title="{{ translate('messages.react_landing_page') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span
                                            class="text-truncate">{{ translate('messages.react_landing_page') }}</span>
                                    </a>
                                </li>
                                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/pages/flutter-landing-page-settings*') ? 'active' : '' }}">
                                    <a class="nav-link "
                                       href="{{ route('admin.business-settings.flutter-landing-page-settings','fixed-data') }}"
                                       title="{{ translate('messages.flutter_landing_page') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span
                                            class="text-truncate">{{ translate('messages.flutter_landing_page') }}</span>
                                    </a>
                                </li>

                                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/pages/business-page*') ? 'active' : '' }}">
                                    <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle"
                                       href="javascript:" title="{{ translate('messages.business_pages') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span
                                            class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('messages.business_pages') }}</span>
                                    </a>
                                    <ul class="js-navbar-vertical-aside-submenu nav nav-sub"
                                        style="display:{{ Request::is('admin/business-settings/pages/business-page*') ? 'block' : 'none' }}">
                                        <li class="nav-item {{ Request::is('admin/business-settings/pages/business-page/terms-and-conditions') ? 'active' : '' }}">
                                            <a class="nav-link "
                                               href="{{ route('admin.business-settings.terms-and-conditions') }}"
                                               title="{{ translate('messages.terms_and_condition') }}">
                                                <span class="tio-circle nav-indicator-icon"></span>
                                                <span
                                                    class="text-truncate">{{ translate('messages.terms_and_condition') }}</span>
                                            </a>
                                        </li>

                                        <li class="nav-item {{ Request::is('admin/business-settings/pages/business-page/privacy-policy') ? 'active' : '' }}">
                                            <a class="nav-link "
                                               href="{{ route('admin.business-settings.privacy-policy') }}"
                                               title="{{ translate('messages.privacy_policy') }}">
                                                <span class="tio-circle nav-indicator-icon"></span>
                                                <span
                                                    class="text-truncate">{{ translate('messages.privacy_policy') }}</span>
                                            </a>
                                        </li>

                                        <li class="nav-item {{ Request::is('admin/business-settings/pages/business-page/about-us') ? 'active' : '' }}">
                                            <a class="nav-link " href="{{ route('admin.business-settings.about-us') }}"
                                               title="{{ translate('messages.about_us') }}">
                                                <span class="tio-circle nav-indicator-icon"></span>
                                                <span class="text-truncate">{{ translate('messages.about_us') }}</span>
                                            </a>
                                        </li>
                                        <li class="nav-item {{ Request::is('admin/business-settings/pages/business-page/refund') ? 'active' : '' }}">
                                            <a class="nav-link " href="{{ route('admin.business-settings.refund') }}"
                                               title="{{ translate('messages.Refund Policy') }}">
                                                <span class="tio-circle nav-indicator-icon"></span>
                                                <span class="text-truncate">{{ translate('Refund Policy') }}</span>
                                            </a>
                                        </li>

                                        <li class="nav-item {{ Request::is('admin/business-settings/pages/business-page/cancelation') ? 'active' : '' }}">
                                            <a class="nav-link "
                                               href="{{ route('admin.business-settings.cancelation') }}"
                                               title="{{ translate('messages.Cancelation Policy') }}">
                                                <span class="tio-circle nav-indicator-icon"></span>
                                                <span class="text-truncate">{{ translate('Cancelation Policy') }}</span>
                                            </a>
                                        </li>


                                        <li class="nav-item {{ Request::is('admin/business-settings/pages/business-page/shipping-policy') ? 'active' : '' }}">
                                            <a class="nav-link "
                                               href="{{ route('admin.business-settings.shipping-policy') }}"
                                               title="{{ translate('messages.shipping_policy') }}">
                                                <span class="tio-circle nav-indicator-icon"></span>
                                                <span class="text-truncate">{{ translate('Shipping Policy') }}</span>
                                            </a>
                                        </li>
                                    </ul>
                                </li>
                            </ul>
                        </li>
                        <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/file-manager*') ? 'active' : '' }}">
                            <a class="nav-link " href="{{ route('admin.business-settings.file-manager.index') }}"
                               title="{{ translate('messages.gallery') }}">
                                <span class="tio-album nav-icon"></span>
                                <span class="text-truncate text-capitalize">{{ translate('messages.gallery') }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <small class="nav-subtitle"
                                   title="{{ translate('messages.business_settings') }}">{{ translate('messages.system_management') }}</small>
                            <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                        </li>
                        <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/third-party*') || Request::is('admin/business-settings/fcm*') || Request::is('admin/business-settings/login-url-setup*') || Request::is('admin/business-settings/offline-payment*') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:"
                               title="{{ translate('messages.3rd_party_&_configurations') }}">
                                <span class="nav-icon tio-account-square-outlined"></span>
                                <span
                                    class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('messages.3rd_party_&_configurations') }}</span>
                            </a>
                            <ul class="js-navbar-vertical-aside-submenu nav nav-sub"
                                style="display:{{ Request::is('admin/business-settings/third-party*')|| Request::is('admin/business-settings/fcm*') ||Request::is('admin/business-settings/login-url-setup*') || Request::is('admin/business-settings/offline-payment*') ? 'block' : 'none' }}">
                                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/third-party*') ? 'active' : '' }}">
                                    <a class="nav-link "
                                       href="{{ route('admin.business-settings.third-party.payment-method') }}"
                                       title="{{ translate('messages.3rd_party') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate">{{ translate('messages.3rd_party') }}</span>
                                    </a>
                                </li>
                                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/fcm*') ? 'active' : '' }}">
                                    <a class="nav-link " href="{{ route('admin.business-settings.fcm-index') }}"
                                       title="{{ translate('messages.firebase_notification') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span
                                            class="text-truncate">{{ translate('messages.firebase_notification') }}</span>
                                    </a>
                                </li>
                                <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/login-url-setup*') ? 'active' : '' }}">
                                    <a class="nav-link " href="{{ route('admin.business-settings.login_url_page') }}"
                                       title="{{ translate('messages.login_url_page') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate">{{ translate('messages.login_url_page') }}</span>
                                    </a>
                                </li>
                                @if (\App\CentralLogics\Helpers::get_mail_status('offline_payment_status'))
                                    <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/offline*') ? 'active' : '' }}">
                                        <a class="nav-link " href="{{ route('admin.business-settings.offline') }}"
                                           title="{{ translate('messages.Offline_Payment_Setup') }}">
                                            <span class="tio-circle nav-indicator-icon"></span>
                                            <span
                                                class="text-truncate">{{ translate('messages.Offline_Payment_Setup') }}</span>
                                        </a>
                                    </li>
                                @endif
                            </ul>
                        </li>
                        <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/react*') ? 'active' : '' }}">
                            <a class="nav-link " href="{{ route('admin.business-settings.react-setup') }}"
                               title="{{ translate('messages.react_site') }}">
                                <span class="tio-rear-window-defrost nav-icon"></span>
                                <span class="text-truncate">{{ translate('messages.react_site') }}</span>
                            </a>
                        </li>
                        <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/email-setup*') ? 'active' : '' }}">
                            <a class="nav-link "
                               href="{{ route('admin.business-settings.email-setup',['admin','forgot-password']) }}"
                               title="{{ translate('messages.email_template') }}">
                                <span class="tio-email nav-icon"></span>
                                <span class="text-truncate">{{ translate('messages.email_template') }}</span>
                            </a>
                        </li>
                        <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/business-settings/app-settings*') ? 'active' : '' }}">
                            <a class="nav-link " href="{{ route('admin.business-settings.app-settings') }}"
                               title="{{ translate('messages.app_settings') }}">
                                <span class="tio-android nav-icon"></span>
                                <span class="text-truncate">{{ translate('messages.app_settings') }}</span>
                            </a>
                        </li>

                        <li class="navbar-vertical-aside-has-menu  @yield('notification_setup')">
                            <a class="nav-link " href="{{ route('admin.business-settings.notification_setup') }}"
                               title="{{ translate('messages.Notification_Channels') }} ">
                                <span class="tio-snooze-notification  nav-icon"></span>
                                <span class="text-truncate">{{ translate('messages.Notification_Channels') }}
                        </span>
                            </a>
                        </li>

                        <li class="navbar-vertical-aside-has-menu {{Request::is('admin/business-settings/db-index')?'active':''}}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link"
                               href="{{route('admin.business-settings.db-index')}}"
                               title="{{translate('messages.clean_database')}}">
                                <i class="tio-cloud nav-icon"></i>
                                <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                            {{translate('messages.clean_database')}}
                        </span>
                            </a>
                        </li>
                    @endif

                    <!-- Dashboards -->
                    <li class="navbar-vertical-aside-has-menu {{Request::is('admin/business-settings/system-addon')?'show active':''}}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link"
                           href="{{route('admin.business-settings.system-addon.index')}}"
                           title="{{translate('system_addons')}}">
                            <i class="tio-add-circle-outlined nav-icon"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                        {{translate('system_addons')}}
                    </span>
                        </a>
                    </li>
                    <!-- End Dashboards -->


                    @if(count(config('addon_admin_routes'))>0)
                        <li class="nav-item">
                            <small
                                class="nav-subtitle">{{translate('messages.addon_menus')}}</small>
                            <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                        </li>
                        <li class="navbar-vertical-aside-has-menu {{Request::is('admin/payment/configuration/*') || Request::is('admin/sms/configuration/*')?'active':''}}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:">
                                <i class="tio-puzzle nav-icon"></i>
                                <span
                                    class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{translate('Addon Menus')}}</span>
                            </a>
                            <ul class="js-navbar-vertical-aside-submenu nav nav-sub"
                                style="display: {{Request::is('admin/payment/configuration/*') || Request::is('admin/sms/configuration/*')?'block':'none'}}">
                                @foreach(config('addon_admin_routes') as $routes)
                                    @foreach($routes as $route)
                                        <li class="navbar-vertical-aside-has-menu {{Request::is($route['path'])  ? 'active' :''}}">
                                            <a class="js-navbar-vertical-aside-menu-link nav-link "
                                               href="{{ $route['url'] }}" title="{{ translate($route['name']) }}">
                                                <span class="tio-circle nav-indicator-icon"></span>
                                                <span class="text-truncate">{{ translate($route['name']) }}</span>
                                            </a>
                                        </li>
                                    @endforeach
                                @endforeach
                            </ul>
                        </li>
                    @endif
                    <!--addon end-->
                    <!-- End web & adpp Settings -->

                    <li class="nav-item py-5">

                    </li>


                    <li class="__sidebar-hs-unfold px-2" id="tourb-9">
                        <div class="hs-unfold w-100">
                            <a class="js-hs-unfold-invoker navbar-dropdown-account-wrapper" href="javascript:;"
                               data-hs-unfold-options='{
                                    "target": "#accountNavbarDropdown",
                                    "type": "css-animation"
                                }'>
                                <div class="cmn--media right-dropdown-icon d-flex align-items-center">
                                    <div class="avatar avatar-sm avatar-circle">
                                        <img class="avatar-img onerror-image"
                                             data-onerror-image="{{asset('public/assets/admin/img/160x160/img1.jpg')}}"

                                             src="{{auth('admin')->user()?->toArray()['image_full_url']}}"

                                             alt="Image Description">
                                        <span class="avatar-status avatar-sm-status avatar-status-success"></span>
                                    </div>
                                    <div class="media-body pl-3">
                                    <span class="card-title h5">
                                        {{auth('admin')->user()->f_name}}
                                        {{auth('admin')->user()->l_name}}
                                    </span>
                                        <span class="card-text">{{auth('admin')->user()->email}}</span>
                                    </div>
                                </div>
                            </a>

                            <div id="accountNavbarDropdown"
                                 class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-right navbar-dropdown-menu navbar-dropdown-account min--240">
                                <div class="dropdown-item-text">
                                    <div class="media align-items-center">
                                        <div class="avatar avatar-sm avatar-circle mr-2">
                                            <img class="avatar-img onerror-image"
                                                 data-onerror-image="{{asset('public/assets/admin/img/160x160/img1.jpg')}}"

                                                 src="{{auth('admin')->user()?->toArray()['image_full_url']}}"

                                                 alt="Image Description">
                                        </div>
                                        <div class="media-body">
                                            <span class="card-title h5">{{auth('admin')->user()->f_name}}</span>
                                            <span class="card-text">{{auth('admin')->user()->email}}</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="dropdown-divider"></div>

                                <a class="dropdown-item" href="{{route('admin.settings')}}">
                                    <span class="text-truncate pr-2"
                                          title="Settings">{{translate('messages.settings')}}</span>
                                </a>

                                <div class="dropdown-divider"></div>

                                <a class="dropdown-item log-out" href="javascript:">
                                    <span class="text-truncate pr-2"
                                          title="Sign out">{{translate('messages.sign_out')}}</span>
                                </a>
                            </div>
                        </div>
                    </li>
                </ul>
            </div>
            <!-- End Content -->
        </div>
    </aside>
</div>

<div id="sidebarCompact" class="d-none">

</div>


@push('script_2')
    <script>
        $(window).on('load', function () {
            if ($(".navbar-vertical-content li.active").length) {
                $('.navbar-vertical-content').animate({
                    scrollTop: $(".navbar-vertical-content li.active").offset().top - 150
                }, 10);
            }
        });

        var $rows = $('#navbar-vertical-content li');
        $('#search-sidebar-menu').keyup(function () {
            var val = $.trim($(this).val()).replace(/ +/g, ' ').toLowerCase();

            $rows.show().filter(function () {
                var text = $(this).text().replace(/\s+/g, ' ').toLowerCase();
                return !~text.indexOf(val);
            }).hide();
        });


        $(document).ready(function () {
            const $searchInput = $('#search');
            const $suggestionsList = $('#search-suggestions');
            const $rows = $('#navbar-vertical-content li');
            const $subrows = $('#navbar-vertical-content li ul li');
            {{--const suggestions = ['{{strtolower(translate('messages.zone'))  }}', '{{ strtolower(translate('messages.setting'))  }}', '{{ strtolower(translate('messages.pages')) }}', '{{ strtolower(translate('messages.3rd_party')) }}','{{ strtolower(translate('messages.system')) }}' ];--}}
            const focusInput = () => updateSuggestions($searchInput.val());
            const hideSuggestions = () => $suggestionsList.slideUp(700);
            const showSuggestions = () => $suggestionsList.slideDown(700);
            let clickSuggestion = function () {
                let suggestionText = $(this).text();
                $searchInput.val(suggestionText);
                hideSuggestions();
                filterItems(suggestionText.toLowerCase());
                updateSuggestions(suggestionText);
            };
            let filterItems = (val) => {
                let unmatchedItems = $rows.show().filter((index, element) => !~$(element).text().replace(
                    /\s+/g, ' ').toLowerCase().indexOf(val));
                let matchedItems = $rows.show().filter((index, element) => ~$(element).text().replace(/\s+/g,
                    ' ').toLowerCase().indexOf(val));
                unmatchedItems.hide();
                matchedItems.each(function () {
                    let $submenu = $(this).find($subrows);
                    let keywordCountInRows = 0;
                    $rows.each(function () {
                        let rowText = $(this).text().toLowerCase();
                        let valLower = val.toLowerCase();
                        let keywordCountRow = rowText.split(valLower).length - 1;
                        keywordCountInRows += keywordCountRow;
                    });
                    if ($submenu.length > 0) {
                        $subrows.show();
                        $submenu.each(function () {
                            let $submenu2 = !~$(this).text().replace(/\s+/g, ' ')
                                .toLowerCase().indexOf(val);
                            if ($submenu2 && keywordCountInRows <= 2) {
                                $(this).hide();
                            }
                        });
                    }
                });
            };
            let updateSuggestions = (val) => {
                $suggestionsList.empty();
                suggestions.forEach(suggestion => {
                    if (suggestion.toLowerCase().includes(val.toLowerCase())) {
                        $suggestionsList.append(
                            `<span class="search-suggestion badge badge-soft-light m-1 fs-14">${suggestion}</span>`
                        );
                    }
                });
                // showSuggestions();
            };
            $searchInput.focus(focusInput);
            $searchInput.on('input', function () {
                updateSuggestions($(this).val());
            });
            $suggestionsList.on('click', '.search-suggestion', clickSuggestion);
            $searchInput.keyup(function () {
                filterItems($(this).val().toLowerCase());
            });
            $searchInput.on('focusout', hideSuggestions);
            $searchInput.on('focus', showSuggestions);
        });

    </script>
@endpush
