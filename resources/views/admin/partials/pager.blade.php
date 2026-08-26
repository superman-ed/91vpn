{{-- 统一分页页脚:$p 为分页器。收敛各后台列表页重复的 hasPages()+links 片段 --}}
@if($p->hasPages())<div class="adm-foot">{{ $p->links('pagination::bootstrap-4') }}</div>@endif
