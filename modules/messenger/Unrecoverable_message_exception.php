<?php

/**
 * Thrown by a handler when trying again can't help (the record is gone, the
 * input is invalid): the message fails at once, without retries.
 */
class Unrecoverable_message_exception extends RuntimeException {
}
