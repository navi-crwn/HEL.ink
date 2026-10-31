@props(['app' => null])
<details class="hop-switcher" data-hop-switcher>
    <summary aria-label="{{ __('HOP Family') }}" title="{{ __('HOP Family') }}">
        <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="3" width="6" height="6" rx="1.5"/><rect x="15" y="3" width="6" height="6" rx="1.5"/><rect x="3" y="15" width="6" height="6" rx="1.5"/><rect x="15" y="15" width="6" height="6" rx="1.5"/></svg>
    </summary>
    <nav class="hop-switcher-panel" aria-label="{{ __('HOP Family') }}">
        <strong>{{ __('HOP Family') }}</strong>
        @foreach (\Hop\Ui\Assets::family($app) as $familyApp)
            <a href="{{ $familyApp['url'] }}" @if($familyApp['current']) aria-current="true" @endif>
                <img src="{{ $familyApp['icons']['mark']['url'] }}" width="32" height="32" alt="" loading="lazy">
                <span>{{ $familyApp['name'] }}</span>
            </a>
        @endforeach
    </nav>
</details>
@once
<style nonce="{{ \Hop\Ui\Assets::nonce() }}">
.hop-switcher{position:relative;flex:none;color:inherit}.hop-switcher>summary{list-style:none;display:grid;place-items:center;cursor:pointer;width:36px;height:36px;border-radius:12px}.hop-switcher>summary::-webkit-details-marker{display:none}.hop-switcher>summary:hover,.hop-switcher[open]>summary{background:rgb(99 102 241 / .12)}.hop-switcher-panel{position:absolute;inset-inline-end:0;top:calc(100% + 12px);width:260px;max-width:calc(100vw - 24px);padding:12px;border:1px solid #cbd5e1;border-radius:20px;background:#fff;color:#172033;box-shadow:0 18px 55px #0f172a26;z-index:80}.hop-switcher-panel>strong{display:block;padding:6px 10px;font-size:13px}.hop-switcher-panel>a{display:flex;align-items:center;gap:12px;padding:10px;border-radius:12px;font-size:14px;text-decoration:none;color:inherit}.hop-switcher-panel>a:hover,.hop-switcher-panel>a[aria-current]{background:#6366f115}.hop-switcher-panel img{object-fit:contain}.hop-switcher :focus-visible{outline:2px solid #6366f1;outline-offset:3px}.dark .hop-switcher-panel{background:#151b2b;color:#f1f5f9;border-color:#394155}@media(max-width:480px){.hop-switcher-panel{position:fixed;inset-inline:12px;top:78px;width:auto;max-width:none}}
</style>
<script nonce="{{ \Hop\Ui\Assets::nonce() }}">
document.addEventListener('keydown',function(e){document.querySelectorAll('[data-hop-switcher][open]').forEach(function(d){if(e.key==='Escape'){d.open=false;d.querySelector('summary').focus();e.preventDefault();}else if(d.contains(e.target)&&['ArrowDown','ArrowUp','Home','End'].includes(e.key)){var links=Array.from(d.querySelectorAll('a')),i=links.indexOf(document.activeElement);i=e.key==='Home'?0:e.key==='End'?links.length-1:e.key==='ArrowDown'?(i+1)%links.length:(i<=0?links.length:i)-1;links[i]?.focus();e.preventDefault();}});});
document.addEventListener('click',function(e){document.querySelectorAll('[data-hop-switcher][open]').forEach(function(d){if(!d.contains(e.target))d.open=false;});});
</script>
@endonce
