@if ($settings->is_resale_license_active)
    @if (auth()->user()->isAdminFromSession())
        <div class="flex justify-center mx-10">
            <div x-data>
                <div class="flex gap-2">
                    <h1>Subscription</h1>
                    <livewire:switch-team />
                    @if (subscriptionProvider() === 'stripe' && $alreadySubscribed)
                        <x-forms.button wire:click='stripeCustomerPortal'>Manage My Subscription</x-forms.button>
                    @endif
                </div>
                <div class="flex items-center pb-8">
                    <span>Currently active team: <span
                            class="text-warning">{{ session('currentTeam.name') }}</span></span>
                </div>
                @if (request()->query->get('cancelled'))
                    <div class="mb-6 rounded alert alert-error">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 stroke-current shrink-0" fill="none"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Something went wrong with your subscription. Please try again or contact
                            support.</span>
                    </div>
                @endif

                @if (config('subscription.provider') !== null)
                    <livewire:subscription.pricing-plans />
                @endif
            </div>
        </div>
    @else
        <div class="flex flex-col justify-center mx-10">
            <div class="flex gap-2">
                <h1>Subscription</h1>
                <livewire:switch-team />
            </div>
            <div class="flex items-center pb-8">
                <span>Currently active team: <span class="text-warning">{{ session('currentTeam.name') }}</span></span>
            </div>
            <div>You are not an admin or have been removed from this team. If this does not make sense, please <span
                    class="text-white underline cursor-pointer" wire:click="help" onclick="help.showModal()">contact
                    us</span>.</div>
        </div>
    @endif
@else
    <div class="px-10">Resale license is not active. Please contact your instance admin.</div>
@endif
