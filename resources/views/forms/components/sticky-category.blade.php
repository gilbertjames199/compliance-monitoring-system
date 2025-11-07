<div
    x-data
    x-init="
        // Attach to Filament’s scroll container
        const container = document.querySelector('.fi-main');
        if (container) {
            const header = $el.querySelector('.sticky-header');
            container.addEventListener('scroll', () => {
                const scrollTop = container.scrollTop;
                if (scrollTop > 0) {
                    header.classList.add('shadow-md');
                } else {
                    header.classList.remove('shadow-md');
                }
            });
        }
    "
>
    <div class="sticky-header sticky top-0 z-50 bg-white border-b p-4 transition-shadow">
        {{ $getChildComponentContainer() }}
    </div>
</div>
