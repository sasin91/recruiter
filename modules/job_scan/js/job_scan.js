// Job scan: loads the next posts when the "Show more" link scrolls into
// view (or is clicked), and puts them, with the next link, in its place.
// Without JS the link opens the next posts as a page.

const list = document.querySelector('.posts');

async function load(link) {
    if (link.dataset.loading) return;
    link.dataset.loading = '1';
    link.textContent = 'Loading…';
    try {
        const response = await fetch(link.dataset.more, { headers: { Accept: 'text/html' } });
        if (!response.ok) throw new Error(response.statusText);
        const batch = document.createElement('template');
        batch.innerHTML = await response.text();
        // A post shows once, even when a batch is loaded twice.
        for (const post of batch.content.querySelectorAll('article[id]')) {
            if (document.getElementById(post.id)) post.remove();
        }
        observer.unobserve(link);
        link.replaceWith(batch.content);
        watch();
    } catch {
        delete link.dataset.loading;
        link.textContent = "Couldn't load more. Try again";
    }
}

const observer = new IntersectionObserver((entries) => {
    for (const entry of entries) {
        if (entry.isIntersecting) load(entry.target);
    }
}, { rootMargin: '600px 0px' });

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
