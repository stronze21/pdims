<?php

namespace App\Http\Middleware;

use Closure;

/** Include the submission client on every HTML entry point, including legacy layouts. */
class SubmissionScripts
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);
        if ($request->isMethod('GET') && strpos((string) $response->headers->get('Content-Type'), 'text/html') !== false
            && method_exists($response, 'getContent') && is_string($response->getContent())) {
            $html = $response->getContent();
            if (stripos($html, '</head>') !== false) {
                // Change the URL with the file contents so cached clients receive guard fixes.
                $scriptPath = public_path('js/submissions.js');
                $version = is_file($scriptPath) ? hash_file('sha256', $scriptPath) : '1';
                $script = '<script src="' . e(asset('js/submissions.js') . '?v=' . $version) . '" defer></script>';
                $response->setContent(preg_replace('~</head>~i', $script . '</head>', $html, 1));
            }
        }
        return $response;
    }
}
