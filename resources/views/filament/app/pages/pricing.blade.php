<x-filament-panels::page>
@vite(['resources/css/app.css', 'resources/js/app.js'])
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-slate-200 py-8 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto">
            <!-- Page Header -->
            <div class="text-center mb-12">
                <h1 class="text-3xl sm:text-4xl font-extrabold text-gray-900 mb-4 leading-tight">
                    {{ __('AI Model Subscription Plans') }}
                </h1>
                <p class="text-base sm:text-lg text-gray-600 max-w-2xl mx-auto mb-8 leading-relaxed">
                    {{ __('Choose the AI model subscription plan that suits you and enjoy powerful AI capabilities and services') }}
                </p>

                <!-- Billing Cycle Toggle -->
                <div class="flex justify-center items-center bg-white rounded-xl p-1 shadow-md max-w-xs mx-auto mb-8">
                    <button
                        wire:click="$set('annual', false)"
                        class="flex-1 py-2 px-4 sm:py-3 sm:px-6 rounded-lg font-semibold text-xs sm:text-sm transition-all duration-200 text-center cursor-pointer {{ !$annual ? 'bg-gradient-to-r from-blue-600 to-blue-800 text-white shadow-lg' : 'bg-transparent text-gray-600 hover:bg-gray-100 hover:text-gray-900' }}">
                        {{ __('Monthly') }}
                    </button>
                    <button
                        wire:click="$set('annual', true)"
                        class="flex-1 py-2 px-4 sm:py-3 sm:px-6 rounded-lg font-semibold text-xs sm:text-sm transition-all duration-200 text-center cursor-pointer {{ $annual ? 'bg-gradient-to-r from-blue-600 to-blue-800 text-white shadow-lg' : 'bg-transparent text-gray-600 hover:bg-gray-100 hover:text-gray-900' }}">
                        {{ __('Annual') }} <span class="text-green-500 font-bold ml-1">-30%</span>
                    </button>
                </div>
            </div>

            @if (empty($this->plans))
                <!-- Empty State -->
                <div class="text-center py-16 px-8 bg-white rounded-xl shadow-lg">
                    <div class="w-16 h-16 mx-auto mb-6 text-gray-400">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-2">{{ __('No Available Subscription Plans') }}</h3>
                    <p class="text-gray-600 leading-relaxed">{{ __('Please create AI model subscription plans in the Lago system, then refresh this page') }}</p>
                </div>
            @else
                <!-- Plan Cards Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-8">
                    @foreach ($this->plans as $index => $plan)
                            @php
                            $isPopular = $plan['is_popular'] ?? false;
                            $monthlyPrice = $plan['amount_cents'] / 100;
                            $displayPrice = $annual ? round($monthlyPrice * 12 * 0.7) : $monthlyPrice;

                            // Use plan data from local database
                            $description = $plan['description'] ?? __('AI model subscription service');
                            $features = $plan['features'] ?? ['Standard AI model access permissions'];
                            $highlights = $plan['highlights'] ?? [];

                            // Get currency code from plan data
                            $currencyCode = $plan['amount_currency'] ?? 'USD';

                            $btnGradients = [
                                'bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700',
                                'bg-gradient-to-r from-pink-500 to-rose-500 hover:from-pink-600 hover:to-rose-600',
                                'bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-700 hover:to-blue-700',
                                'bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700'
                            ];
                            $btnGradient = $btnGradients[$index % 4];
                        @endphp

                        <!-- 定价卡片 -->
                        <div class="bg-white rounded-xl shadow-lg overflow-hidden transition-all duration-300 border-2 {{ $isPopular ? 'border-yellow-400' : 'border-transparent' }} hover:-translate-y-1 hover:shadow-2xl {{ $isPopular ? 'relative' : '' }}">
                            @if($isPopular)
                                <!-- Recommended Badge -->
                                <div class="absolute -top-0.5 right-5 bg-gradient-to-r from-yellow-400 to-yellow-600 text-white py-2 px-4 rounded-bl-lg rounded-br-lg font-semibold text-xs z-10 shadow-lg">
                                    ⭐ {{ __('Recommended Plan') }}
                                </div>
                            @endif

                            <div class="p-6 sm:p-8">
                                <!-- Icon -->
                                <div class="w-12 h-12 sm:w-16 sm:h-16 mx-auto mb-4 sm:mb-6 bg-gradient-to-r from-blue-600 to-blue-800 rounded-xl flex items-center justify-center text-white">
                                    <svg class="w-6 h-6 sm:w-8 sm:h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                                    </svg>
                                </div>

                                <!-- Plan Name -->
                                <h3 class="text-lg sm:text-xl font-bold text-gray-900 text-center mb-2">{{ $plan['name'] }}</h3>
                                <p class="text-gray-600 text-center mb-6 sm:mb-8 leading-relaxed text-sm sm:text-base">{{ $description }}</p>

                                <!-- Price -->
                                <div class="text-center mb-6 sm:mb-8">
                                    <div class="flex items-baseline justify-center gap-1 mb-2">
                                        <span class="text-base sm:text-lg text-gray-600 font-semibold">{{ $currencyCode }}</span>
                                        <span class="text-3xl sm:text-5xl font-extrabold text-gray-900">{{ number_format($displayPrice, 0) }}</span>
                                        <span class="text-base sm:text-lg text-gray-600 font-medium">/ {{ $annual ? 'year' : 'month' }}</span>
                                    </div>
                                    @if($annual)
                                        <p class="text-xs sm:text-sm text-green-600 font-semibold">Save {{ $currencyCode }} {{ number_format($monthlyPrice * 12 * 0.3, 0) }}/year</p>
                                    @endif
                                </div>

                                <!-- Subscribe Button -->
                                <button
                                    wire:click="subscribe('{{ $plan['code'] }}')"
                                    class="w-full py-3 px-6 sm:py-4 sm:px-8 {{ $btnGradient }} text-white font-semibold text-sm sm:text-base text-center transition-all duration-200 cursor-pointer border-none outline-none rounded-xl mb-6 sm:mb-8 transform hover:-translate-y-0.5 hover:shadow-xl">
                                    {{ __('Subscribe Now') }}
                                </button>

                                <!-- Highlights Section (if available) -->
                                @if(!empty($highlights))
                                    <div class="bg-gradient-to-r from-yellow-50 to-orange-50 rounded-lg p-4 mb-6">
                                        <h4 class="text-sm font-semibold text-orange-800 mb-3 flex items-center">
                                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                            </svg>
                                            {{ __('Key Highlights') }}
                                        </h4>
                                        @foreach($highlights as $highlight)
                                            <div class="flex items-start mb-2 last:mb-0">
                                                <span class="text-orange-600 mr-2">✨</span>
                                                <span class="text-sm text-gray-700 leading-relaxed">{{ $highlight }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                <!-- Features List -->
                                <div class="mt-8">
                                    <h4 class="text-base font-bold text-gray-900 mb-4">{{ __('Features Included') }}:</h4>
                                    @if(is_array($features))
                                        @foreach ($features as $feature)
                                            @php
                                                $featureText = is_array($feature) ? ($feature['name'] ?? json_encode($feature)) : $feature;
                                            @endphp
                                            <div class="flex items-start gap-3 mb-3">
                                                <div class="w-5 h-5 mt-0.5 text-green-600 flex-shrink-0">
                                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                                    </svg>
                                                </div>
                                                <span class="text-gray-700 leading-relaxed">{{ $featureText }}</span>
                                            </div>
                                        @endforeach
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <!-- Footer Information -->
            <div class="text-center mt-16">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 max-w-4xl mx-auto sm:px-6 lg:px-8">
                    <h3 class="text-2xl font-bold text-gray-900 mb-4">{{ __('Why Choose Our AI Model Services?') }}</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mt-8">
                        <div class="text-center">
                            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mx-auto mb-4">
                                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                            </div>
                            <h4 class="font-semibold text-gray-900 mb-2">{{ __('High-Performance Models') }}</h4>
                            <p class="text-gray-600 text-sm">{{ __('Access the latest AI models with excellent performance and accuracy') }}</p>
                        </div>
                        <div class="text-center">
                            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mx-auto mb-4">
                                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <h4 class="font-semibold text-gray-900 mb-2">{{ __('Flexible Billing') }}</h4>
                            <p class="text-gray-600 text-sm">{{ __('Pay-as-you-go with monthly and annual options to meet different usage scenarios') }}</p>
                        </div>
                        <div class="text-center">
                            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mx-auto mb-4">
                                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192L5.636 18.364M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <h4 class="font-semibold text-gray-900 mb-2">{{ __('Enterprise Support') }}</h4>
                            <p class="text-gray-600 text-sm">{{ __('Professional technical support and SLA guarantees to ensure business continuity') }}</p>
                        </div>
                    </div>
                    <div class="mt-8 pt-6 border-t border-gray-200">
                        <a href="#" class="inline-flex items-center text-blue-600 hover:text-blue-800 font-semibold transition-colors duration-200">
                            {{ __('View Detailed Service Terms') }}
                            <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
