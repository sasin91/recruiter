<?php
require_once __DIR__ . '/Store_links.php';

/**
 * The Jobnet joblog browser extension (extensions/jobnet-joblog): its page,
 * /joblog_extension, with what it does, where to get it and its privacy
 * policy (the stores link to it), and the banner the candidate's pages show
 * to suggest it (_banner).
 */
class Joblog_extension extends Trongate {

    /**
     * What the extension does, the store link for each browser and its
     * privacy policy. Open to everyone: the stores' reviewers read it.
     *
     * @return void
     */
    public function index(): void {
        $this->view('index', [
            'signed_in' => $this->trongate_tokens->attempt_get_valid_token() !== false,
            'links' => Store_links::all(),
        ]);
    }

    /**
     * The "add the extension" banner, for the pages a signed-in job seeker
     * uses (cv_match, applications, job_scan): drawn hidden, and js/banner.js
     * shows it in a browser that has a store link, unless the extension is
     * already there or the banner was closed before. Nothing while no
     * listing is published.
     *
     * @return void
     */
    public function _banner(): void {
        $links = Store_links::all();
        if ($links === []) {
            return;
        }
        $this->view('banner', ['links' => $links]);
    }

}
