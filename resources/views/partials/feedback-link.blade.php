@if ($feedbackLink = \App\Support\Feedback::hubLink())
    <p class="text-sm"><a href="{{ $feedbackLink }}" class="font-semibold text-indigo-600 underline underline-offset-2 hover:text-indigo-700 dark:text-indigo-400">{{ __('Send feedback') }} &rarr;</a></p>
@endif
