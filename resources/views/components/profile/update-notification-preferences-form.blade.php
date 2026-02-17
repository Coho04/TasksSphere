<?php

use Livewire\Component;
use Illuminate\Support\Facades\Auth;

new class extends Component
{
    /**
     * The state of the form.
     *
     * @var array
     */
    public $state = [];

    /**
     * Mount the component.
     *
     * @return void
     */
    public function mount()
    {
        $user = Auth::user();

        $this->state = [
            'notify_email' => $user->notify_email,
            'notify_push' => $user->notify_push,
        ];
    }

    /**
     * Update the user's notification preferences.
     *
     * @return void
     */
    public function updateNotificationPreferences()
    {
        $this->resetErrorBag();

        $this->validate([
            'state.notify_email' => ['required', 'boolean'],
            'state.notify_push' => ['required', 'boolean'],
        ]);

        $user = Auth::user();

        $user->forceFill([
            'notify_email' => $this->state['notify_email'],
            'notify_push' => $this->state['notify_push'],
        ])->save();

        $this->dispatch('saved');
    }

    /**
     * Get the current user of the application.
     *
     * @return mixed
     */
    public function getUserProperty()
    {
        return Auth::user();
    }
};
?>

<x-form-section submit="updateNotificationPreferences">
    <x-slot name="title">
        {{ __('Notification Preferences') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Manage how you want to receive notifications.') }}
    </x-slot>

    <x-slot name="form">
        <!-- Email Notifications -->
        <div class="col-span-6 sm:col-span-4">
            <div class="flex items-start">
                <div class="flex items-center h-5">
                    <x-checkbox id="notify_email" wire:model="state.notify_email" />
                </div>
                <div class="ms-3 text-sm">
                    <x-label for="notify_email" value="{{ __('Email Notifications') }}" />
                    <p class="text-gray-500 dark:text-gray-400">{{ __('Receive notifications via email.') }}</p>
                </div>
            </div>
            <x-input-error for="state.notify_email" class="mt-2" />
        </div>

        <!-- Push Notifications -->
        <div class="col-span-6 sm:col-span-4 mt-4">
            <div class="flex items-start">
                <div class="flex items-center h-5">
                    <x-checkbox id="notify_push" wire:model="state.notify_push" />
                </div>
                <div class="ms-3 text-sm">
                    <x-label for="notify_push" value="{{ __('Push Notifications') }}" />
                    <p class="text-gray-500 dark:text-gray-400">{{ __('Receive push notifications on your devices.') }}</p>
                </div>
            </div>
            <x-input-error for="state.notify_push" class="mt-2" />
        </div>
    </x-slot>

    <x-slot name="actions">
        <x-action-message class="me-3" on="saved">
            {{ __('Saved.') }}
        </x-action-message>

        <x-button>
            {{ __('Save') }}
        </x-button>
    </x-slot>
</x-form-section>
