// resources/manage: loads the next rows when the "Show more" link scrolls
// into view (or is clicked), and puts them, with the next link, in its row's
// place. Without JS the link opens the next rows as a page.
{
    const list = document.querySelector('.resource-list tbody');

    const observer = new IntersectionObserver((entries) => {
        for (const entry of entries) {
            if (entry.isIntersecting) load(entry.target);
        }
    }, { rootMargin: '600px 0px' });

    async function load(link) {
        if (link.dataset.loading) return;
        link.dataset.loading = '1';
        link.textContent = 'Loading…';
        try {
            const response = await fetch(link.dataset.more, { headers: { Accept: 'text/html' } });
            if (!response.ok) throw new Error(response.statusText);
            const batch = document.createElement('template');
            batch.innerHTML = await response.text();
            // A row comes twice if rows were added meanwhile.
            for (const row of batch.content.querySelectorAll('tr[id]')) {
                if (document.getElementById(row.id)) row.remove();
            }
            observer.unobserve(link);
            link.closest('tr').replaceWith(batch.content);
            watch();
        } catch {
            delete link.dataset.loading;
            link.textContent = "Couldn't load more. Try again";
        }
    }

    function watch() {
        const link = list?.querySelector('a.more[data-more]');
        if (!link || link.dataset.watched) return;
        link.dataset.watched = '1';
        link.addEventListener('click', (event) => {
            event.preventDefault();
            load(link);
        });
        observer.observe(link);
    }

    watch();
}
