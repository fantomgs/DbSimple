<?php
/**
 * User: orbisnull
 * Date: 11.09.13
 */

namespace DbSimple;

/**
 * Support for error tracking.
 * Can hold error messages, error queries and build proper stacktraces.
 */
abstract class LastError
{
    public $error = null;
    public $errmsg = null;
    private $errorHandler = null;
    private $ignoresInTraceRe = 'DbSimple_.*::.* | call_user_func.*';

    /**
     * abstract void _logQuery($query)
     * Must be overriden in derived class.
     */
    abstract protected function _logQuery($query);

    /**
     * void _resetLastError()
     * Reset the last error. Must be called on correct queries.
     */
    protected function _resetLastError()
    {
        $this->error = $this->errmsg = null;
    }

    /**
     * void _setLastError(int $code, string $message, string $query)
     * Fill $this->error property with error information. Error context
     * (code initiated the query outside DbSimple) is assigned automatically.
     */
    protected function _setLastError($code, $msg, $query)
    {
        $context = "unknown";
        if ($t = $this->findLibraryCaller()) {
            $context = (isset($t['file'])? $t['file'] : '?') . ' line ' . (isset($t['line'])? $t['line'] : '?');
        }
        $this->error = array(
            'code'    => $code,
            'message' => rtrim($msg),
            'query'   => $query,
            'context' => $context,
        );
        $this->errmsg = rtrim($msg) . ($context? " at $context" : "");

        $this->_logQuery("  -- error #".$code.": ".preg_replace('/(\r?\n)+/s', ' ', $this->errmsg));

        if (is_callable($this->errorHandler)) {
            call_user_func($this->errorHandler, $this->errmsg, $this->error);
        }

        return false;
    }


    /**
     * callback setErrorHandler(callback $handler)
     * Set new error handler called on database errors.
     * Handler gets 3 arguments:
     * - error message
     * - full error context information (last query etc.)
     */
    public function setErrorHandler($handler)
    {
        $prev = $this->errorHandler;
        $this->errorHandler = $handler;
        // In case of setting first error handler for already existed
        // error - call the handler now (usual after connect()).
        if (!$prev && $this->error && $this->errorHandler) {
            call_user_func($this->errorHandler, $this->errmsg, $this->error);
        }
        return $prev;
    }

    /**
     * void addIgnoreInTrace($reName)
     * Add regular expression matching ClassName::functionName or functionName.
     * Matched stack frames will be ignored in stack traces passed to query logger.
     */
    public function addIgnoreInTrace($name)
    {
        $this->ignoresInTraceRe .= "|" . $name;
    }

    /**
     * array of array findLibraryCaller()
     * Return part of stacktrace before calling first library method.
     * Used in debug purposes (query logging etc.).
     */
    protected function findLibraryCaller()
    {
        $caller = call_user_func(
            array(&$this, 'debug_backtrace_smart'),
            $this->ignoresInTraceRe,
            true
        );
        return $caller;
    }

    /**
     * array debug_backtrace_smart($ignoresRe=null, $returnCaller=false)
     *
     * Return stacktrace. Correctly work with call_user_func*
     * (totally skip them correcting caller references).
     * If $returnCaller is true, return only first matched caller,
     * not all stacktrace.
     *
     * @version 2.03
     */
    private function debug_backtrace_smart($ignoresRe=null, $returnCaller=false)
    {
        $trace = debug_backtrace();

        if ($ignoresRe !== null)
            $ignoresRe = "/^(?>{$ignoresRe})$/six";
        $smart = array();
        $framesSeen = 0;
        for ($i=0, $n=count($trace); $i<$n; $i++) {
            $t = $trace[$i];
            if (!$t) continue;

            // Next frame.
            $next = isset($trace[$i+1])? $trace[$i+1] : null;

            // Dummy frame before call_user_func* frames.
            if (!isset($t['file'])) {
                $t['over_function'] = $trace[$i+1]['function'];
                $t = $t + $trace[$i+1];
                $trace[$i+1] = null; // skip call_user_func on next iteration
                $next = isset($trace[$i+2])? $trace[$i+2] : null; // Correct Next frame.
            }

            // Skip myself frame.
            if (++$framesSeen < 2) continue;

            // 'class' and 'function' field of next frame define where
            // this frame function situated. Skip frames for functions
            // situated in ignored places.
            if ($ignoresRe && $next) {
                // Name of function "inside which" frame was generated.
                $frameCaller = (isset($next['class'])? $next['class'].'::' : '') . (isset($next['function'])? $next['function'] : '');
                if (preg_match($ignoresRe, $frameCaller)) continue;
            }

            // On each iteration we consider ability to add PREVIOUS frame
            // to $smart stack.
            if ($returnCaller) return $t;
            $smart[] = $t;
        }
        return $smart;
    }

}