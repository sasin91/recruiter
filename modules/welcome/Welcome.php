<?php
/**
 * The public landing page: what the CV match does, free and signed in.
 */
class Welcome extends Trongate {

    /**
     * The landing page. Open to everyone; it never redirects, so it can't
     * loop with the sign-in page.
     *
     * @return void
     */
    public function index(): void {
        $data = [
            'signed_in' => $this->trongate_tokens->attempt_get_valid_token() !== false,
        ];
        $this->view('landing', $data);
    }

}
