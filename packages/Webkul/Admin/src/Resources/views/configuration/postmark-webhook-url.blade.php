<div class="mb-4">
    <label class="mb-1.5 flex items-center gap-1 text-xs font-medium text-gray-800 dark:text-white">
        @lang('admin::app.configuration.index.email.postmark.general.webhook-url')
    </label>

    <div class="flex items-center gap-2">
        <input
            type="text"
            id="postmark-webhook-url"
            value="{{ url('webhooks/postmark/inbound') }}"
            class="w-full rounded-md border bg-gray-100 px-3 py-2.5 text-sm text-gray-600 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
            readonly
        />

        <button
            type="button"
            onclick="copyPostmarkWebhookUrl()"
            class="flex h-10 w-10 items-center justify-center rounded-md border border-gray-200 bg-white text-gray-600 transition-all hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
            title="@lang('admin::app.configuration.index.email.postmark.general.copy-url')"
        >
            <i class="icon-copy text-xl"></i>
        </button>
    </div>

    <p class="mt-1.5 text-xs italic text-gray-600 dark:text-gray-300">
        @lang('admin::app.configuration.index.email.postmark.general.webhook-url-info')
    </p>
</div>

@pushOnce('scripts')
    <script>
        function copyPostmarkWebhookUrl() {
            const urlInput = document.getElementById('postmark-webhook-url');

            navigator.clipboard.writeText(urlInput.value).then(() => {
                const button = event.currentTarget;
                const originalIcon = button.innerHTML;

                button.innerHTML = '<i class="icon-done text-xl text-green-500"></i>';

                setTimeout(() => {
                    button.innerHTML = originalIcon;
                }, 2000);
            }).catch(err => {
                // Fallback for browsers that don't support clipboard API
                urlInput.select();
                document.execCommand('copy');
            });
        }
    </script>
@endPushOnce
