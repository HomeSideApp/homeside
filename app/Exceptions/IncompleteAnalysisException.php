<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Class IncompleteAnalysisException
 *
 * This exception is thrown when an AI analysis of a document is incomplete and
 * the operation cannot proceed.
 */
class IncompleteAnalysisException extends RuntimeException {}
