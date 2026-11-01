<?php

namespace App\Services;

/** GitHub answered 403/429: the run stops and every stored number stays. */
class GithubRateLimited extends \RuntimeException {}
