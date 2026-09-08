{{--
    Published from laravel/framework (vendor:publish --tag=laravel-mail) and
    branded for this app: config('app.name') swapped for __('app.name')
    everywhere below, so the header/footer read "Parts Procurement" from
    our own lang file regardless of the APP_NAME env value -- the same
    source the web layout's <title> and header brand span already use --
    and the footer's copyright line reuses the existing app.footer_rights
    key instead of Laravel's own untranslated "All rights reserved." string.
    Colours customized in themes/default.css to match the app's light theme.
--}}
<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ __('app.name') }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ __('app.name') }}. {{ __('app.footer_rights') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
