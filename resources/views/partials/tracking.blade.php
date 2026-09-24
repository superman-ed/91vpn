@php $trackingCode = setting('tracking_code', ''); @endphp
@if(trim($trackingCode) !== '')
{{-- 后台「站点设置 → 统计/追踪代码」里粘贴的第三方脚本(Google Analytics / Plausible / Umami /
     Microsoft Clarity 等),原样注入到公开页 <head>。仅管理员可设,与 support_widget 同为可信原样注入。 --}}
{!! $trackingCode !!}
@endif
