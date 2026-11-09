<x-guest-layout>
    <div class="mb-6">
        <h1 class="page-title">{{ __('Accept the terms to continue') }}</h1>
        <p class="mt-1 text-sm text-muted">{{ __('Before we can create your HOP ID with Google, please accept the HOP Terms of Service (version :version).', ['version' => $termsVersion]) }}</p>
    </div>
    <form method="POST" action="{{ route('auth.google.consent.store') }}">
        @csrf
        <input type="hidden" name="terms_version" value="{{ $termsVersion }}">
        <div class="mt-4">
            <label class="flex items-start space-x-3 cursor-pointer group">
                <input
                    type="checkbox"
                    name="accept"
                    value="1"
                    required
                    class="mt-1 rounded-sm border-gray-300 dark:border-gray-700 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 dark:bg-gray-800 cursor-pointer"
                >
                <span class="text-sm text-gray-600 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-gray-200">
                    {!! __('I agree to the :terms and understand the acceptable use policy, prohibited activities, and my rights and obligations.', [
                        'terms' => '<a href="'.e($termsArtifact['url']).'" target="_blank" rel="noopener" class="text-blue-600 dark:text-blue-400 hover:underline font-medium">'.e(__('Terms of Service (version :version)', ['version' => $termsVersion])).'</a>',
                    ]) !!}
                </span>
            </label>
        </div>
        <div class="mt-6 flex flex-col gap-3">
            <button type="submit" class="btn-primary btn-lg btn-block">
                {{ __('Agree and continue with Google') }}
            </button>
            <a href="{{ route('register') }}" class="text-center text-sm text-muted hover:underline">
                {{ __('Cancel and go back') }}
            </a>
        </div>
    </form>
</x-guest-layout>
