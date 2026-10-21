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
        const batch = document.createElement('template'); // server HTML
        batch.innerHTML = await response.text(); // server HTML
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

// ---------- live list ----------
//
// Trongate MX polls job_scan/latest/{id} (the newest posts) into the hidden
// #posts-latest every 30 seconds while the page is in view, and the page
// reads it at once when it's back in view (another tab, a phone waking, the
// back/forward cache). Each answer is merged into the list: new posts go on
// top, posts whose status or score changed are swapped in place, and the
// "Show more" cursor is left alone, so nothing loaded below is lost. An
// answer sharing no post with the list (more new posts than one batch, or a
// list that was empty) replaces the list, with that batch's own Show more.

const poller = document.getElementById('posts-latest');
const stale = document.getElementById('posts-stale');

window.jobScanMerge = () => {
    const latest = poller.querySelector('.posts-latest');
    poller.replaceChildren();
    if (!latest) {
        // The sign-in page, most likely: the session ran out.
        stale.textContent = "The list can't update: you may have been signed out. Reload the page.";
        stale.hidden = false;
        return;
    }
    stale.hidden = true;
    const posts = [...latest.querySelectorAll(':scope > article[id]')];
    if (!posts.some((post) => document.getElementById(post.id))) {
        if (posts.length || !list.querySelector('article[id]')) {
            list.replaceChildren(...latest.childNodes);
            watch();
        }
    } else {
        let previous = null;
        for (const post of posts) {
            const shown = document.getElementById(post.id);
            if (!shown) {
                if (previous) previous.after(post);
                else list.prepend(post);
            } else if (shown.outerHTML !== post.outerHTML) {
                shown.replaceWith(post);
            } else {
                previous = shown;
                continue;
            }
            previous = post;
        }
    }
    const none = document.getElementById('no-posts');
    if (none) none.hidden = list.querySelector('article[id]') !== null;
};

let readAt = 0;

// Polling stopped, the list read now, polling started again.
function readNow() {
    if (Date.now() - readAt < 2000) return;
    readAt = Date.now();
    TrongateMX.stopPolling(poller);
    poller.click();
    TrongateMX.startPolling(poller, '30s');
}

if (poller) {
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) TrongateMX.stopPolling(poller);
        else readNow();
    });
    window.addEventListener('pageshow', (event) => {
        if (event.persisted && !document.hidden) readNow();
    });
    // Trongate MX starts the polling once the page has loaded; a page opened
    // in a background tab waits until it's looked at.
    document.addEventListener('DOMContentLoaded', () => {
        if (document.hidden) TrongateMX.stopPolling(poller);
    });
}
