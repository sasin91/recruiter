<?php
/**
 * The privacy notice at /privacy: what personal data Recruiter keeps, why,
 * who it goes to, how long, and the rights people have over it (GDPR art. 13).
 * Open to everyone; the sign-up forms, the landing page, the account page and
 * the extension's store listing link here. The owner's name and email come
 * from config/site_owner.php (OUR_NAME, OUR_EMAIL_ADDRESS in the environment).
 */
class Privacy extends Trongate {

    /** When the notice last changed in substance; bump it with the text. */
    const UPDATED = '2026-10-25';

    /**
     * The notice.
     *
     * @return void
     */
    public function index(): void {
        $data = [
            'owner' => OUR_NAME,
            'email' => OUR_EMAIL_ADDRESS,
            'updated' => date('j F Y', strtotime(self::UPDATED)),
        ];
        $this->view('privacy', $data);
    }

}
