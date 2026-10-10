<?php

/**
 * Thrown by a job method when trying again can't help (the record is gone, the
 * input is invalid): the job fails at once, without retries.
 */
class Unrecoverable_job_exception extends RuntimeException {
}
