<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Existing public UCP API/drop-in symbols are intentionally preserved for backward compatibility.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Pragmatic, dependency-free HTML tokenizer for safe tag rewriting.
 *
 * UltraCache's markup passes historically used `UCP_Helpers::safe_preg_replace_callback('/<img\b([^>]*)>/i', ...)`
 * style regexes. That approach has two well-known failure modes:
 *
 *   1. It rewrites tags that only *look* like markup but live inside raw-text/RCDATA elements
 *      (`<script>`, `<style>`, `<textarea>`, `<title>`) or inside comments / CDATA, e.g. a string
 *      `'<img src=x>'` in inline JavaScript.
 *   2. `[^>]*` stops at the first `>`, so it mangles tags whose attribute values legitimately
 *      contain `>` — e.g. `<img alt="a > b" src="x.jpg">`.
 *
 * This tokenizer scans the document with a small state machine that understands raw-text elements,
 * comments, CDATA and quoted attribute values, then rewrites only genuine occurrences of a target
 * tag in data context. It is intentionally not a full HTML5 tree builder: it does not normalise or
 * re-serialise the document, it only locates real start tags and lets a callback rewrite them,
 * preserving everything else byte-for-byte. It jumps between `<` boundaries rather than walking
 * every character, so throughput stays close to the regex it replaces.
 *
 * Exposed as an opt-in engine behind the `enable_html_parser` option; the regex path remains the
 * default and the guaranteed fallback.
 */
class UCP_HTML_Parser {

    /**
     * Elements whose content is raw text / RCDATA: their contents are never parsed as markup, so a
     * `<img>` appearing inside them must not be rewritten.
     *
     * @var array<string,bool>
     */
    private static $raw_text_elements = array(
        'script'   => true,
        'style'    => true,
        'textarea' => true,
        'title'    => true,
        'noscript' => true,
        'pre'      => true,
    );

    /**
     * Rewrite every genuine `<$tag ...>` start tag in $html via $callback.
     *
     * The callback receives a regex-`preg_replace_callback`-compatible array so existing UltraCache
     * callbacks work unchanged: index 0 is the full start tag, index 1 is the attribute string
     * (everything between the tag name and the closing `>`, excluding any trailing self-closing `/`).
     * The callback returns the replacement string for that tag.
     *
     * Tags inside raw-text elements, comments and CDATA are left untouched. On any unexpected
     * condition the scanner copies input through verbatim, so output can never be more broken than
     * the input.
     *
     * @param string   $html     Markup to process.
     * @param string   $tag      Lowercase tag name to match (e.g. 'img').
     * @param callable $callback function(array $matches): string
     * @return string
     */
    public static function replace_tag($html, $tag, $callback) {
        if (!is_scalar($tag) && null !== $tag) {
            $tag = '';
        }
        if (!is_string($html) || '' === $html || !is_callable($callback)) {
            return $html;
        }
        $tag = strtolower($tag);
        $len = strlen($html);
        $out = '';
        $i   = 0;

        while ($i < $len) {
            $lt = strpos($html, '<', $i);
            if (false === $lt) {
                $out .= substr($html, $i);
                break;
            }
            // Copy text before the '<'.
            $out .= substr($html, $i, $lt - $i);
            $i = $lt;

            // Comment: <!-- ... -->
            if (0 === substr_compare($html, '<!--', $i, 4)) {
                $end = strpos($html, '-->', $i + 4);
                $end = (false === $end) ? $len : $end + 3;
                $out .= substr($html, $i, $end - $i);
                $i = $end;
                continue;
            }
            // CDATA: <![CDATA[ ... ]]>
            if (0 === substr_compare($html, '<![CDATA[', $i, 9)) {
                $end = strpos($html, ']]>', $i + 9);
                $end = (false === $end) ? $len : $end + 3;
                $out .= substr($html, $i, $end - $i);
                $i = $end;
                continue;
            }
            // Other declarations / doctype: <! ... >
            if ($i + 1 < $len && '!' === $html[$i + 1]) {
                $end = strpos($html, '>', $i + 1);
                $end = (false === $end) ? $len : $end + 1;
                $out .= substr($html, $i, $end - $i);
                $i = $end;
                continue;
            }
            // End tag: </name>
            if ($i + 1 < $len && '/' === $html[$i + 1]) {
                $end = strpos($html, '>', $i + 1);
                $end = (false === $end) ? $len : $end + 1;
                $out .= substr($html, $i, $end - $i);
                $i = $end;
                continue;
            }

            $name = self::read_tag_name($html, $i + 1, $len);
            if ('' === $name) {
                // A stray '<' that is not a tag start. Emit it literally and advance.
                $out .= '<';
                $i++;
                continue;
            }

            // Find the end of this start tag, respecting quoted attribute values.
            $tag_end = self::find_start_tag_end($html, $i, $len);
            if (false === $tag_end) {
                // Unterminated tag: copy the remainder verbatim and stop.
                $out .= substr($html, $i);
                break;
            }
            $full = substr($html, $i, $tag_end - $i + 1); // includes leading '<' and trailing '>'
            $name_lc = strtolower($name);

            if ($name_lc === $tag) {
                $out .= self::apply_start_tag_callback($full, $callback);
                if (isset(self::$raw_text_elements[$name_lc])) {
                    // The opening tag may be the rewrite target, but its raw-text body is never
                    // markup and must remain byte-for-byte untouched.
                    $i = $tag_end + 1;
                    $close = '</' . $name_lc;
                    $close_pos = stripos($html, $close, $i);
                    if (false === $close_pos) {
                        $out .= substr($html, $i);
                        break;
                    }
                    $close_end = strpos($html, '>', $close_pos);
                    $close_end = (false === $close_end) ? $len : $close_end + 1;
                    $out .= substr($html, $i, $close_end - $i);
                    $i = $close_end;
                    continue;
                }
            } elseif (isset(self::$raw_text_elements[$name_lc])) {
                // Emit the start tag, then skip to the matching close tag verbatim so nothing inside
                // the raw-text element is ever treated as markup.
                $out .= $full;
                $i = $tag_end + 1;
                $close = '</' . $name_lc;
                $close_pos = stripos($html, $close, $i);
                if (false === $close_pos) {
                    $out .= substr($html, $i);
                    break;
                }
                $close_end = strpos($html, '>', $close_pos);
                $close_end = (false === $close_end) ? $len : $close_end + 1;
                $out .= substr($html, $i, $close_end - $i);
                $i = $close_end;
                continue;
            } else {
                $out .= $full;
            }

            $i = $tag_end + 1;
        }

        return $out;
    }

    /**
     * Rewrite every genuine `<$tag ...>...</$tag>` element via $callback.
     *
     * The callback receives a preg_replace_callback-compatible array: index 0 is the full element,
     * index 1 is the opening-tag attribute string and index 2 is the element body. This is designed
     * as a safe drop-in for existing element regex callbacks such as iframe lazy-loading. Elements
     * inside raw-text containers, comments and CDATA are skipped. Nested same-name elements are
     * counted so the first matching close tag inside content does not truncate the element.
     *
     * @param string   $html
     * @param string   $tag
     * @param callable $callback function(array $matches): string
     * @return string
     */
    public static function replace_element($html, $tag, $callback) {
        if (!is_scalar($tag) && null !== $tag) {
            $tag = '';
        }
        if (!is_string($html) || '' === $html || !is_callable($callback)) {
            return $html;
        }
        $tag = strtolower($tag);
        $len = strlen($html);
        $out = '';
        $i   = 0;

        while ($i < $len) {
            $lt = strpos($html, '<', $i);
            if (false === $lt) {
                $out .= substr($html, $i);
                break;
            }
            $out .= substr($html, $i, $lt - $i);
            $i = $lt;

            if (0 === substr_compare($html, '<!--', $i, 4)) {
                $end = strpos($html, '-->', $i + 4);
                $end = (false === $end) ? $len : $end + 3;
                $out .= substr($html, $i, $end - $i);
                $i = $end;
                continue;
            }
            if (0 === substr_compare($html, '<![CDATA[', $i, 9)) {
                $end = strpos($html, ']]>', $i + 9);
                $end = (false === $end) ? $len : $end + 3;
                $out .= substr($html, $i, $end - $i);
                $i = $end;
                continue;
            }
            if ($i + 1 < $len && '!' === $html[$i + 1]) {
                $end = strpos($html, '>', $i + 1);
                $end = (false === $end) ? $len : $end + 1;
                $out .= substr($html, $i, $end - $i);
                $i = $end;
                continue;
            }
            if ($i + 1 < $len && '/' === $html[$i + 1]) {
                $end = strpos($html, '>', $i + 1);
                $end = (false === $end) ? $len : $end + 1;
                $out .= substr($html, $i, $end - $i);
                $i = $end;
                continue;
            }

            $name = self::read_tag_name($html, $i + 1, $len);
            if ('' === $name) {
                $out .= '<';
                $i++;
                continue;
            }

            $tag_end = self::find_start_tag_end($html, $i, $len);
            if (false === $tag_end) {
                $out .= substr($html, $i);
                break;
            }

            $full_start = substr($html, $i, $tag_end - $i + 1);
            $name_lc = strtolower($name);

            if ($name_lc === $tag) {
                $element_end = self::find_element_end($html, $tag, $tag_end + 1, $len);
                if (false === $element_end) {
                    $out .= $full_start;
                    $i = $tag_end + 1;
                    continue;
                }
                $close_start = $element_end[0];
                $close_end   = $element_end[1];
                $body = substr($html, $tag_end + 1, $close_start - $tag_end - 1);
                $full = substr($html, $i, $close_end - $i);
                $out .= self::apply_element_callback($full, self::extract_attributes($full_start), $body, $callback);
                $i = $close_end;
                continue;
            }

            if (isset(self::$raw_text_elements[$name_lc])) {
                $out .= $full_start;
                $i = $tag_end + 1;
                $close = '</' . $name_lc;
                $close_pos = stripos($html, $close, $i);
                if (false === $close_pos) {
                    $out .= substr($html, $i);
                    break;
                }
                $close_end = strpos($html, '>', $close_pos);
                $close_end = (false === $close_end) ? $len : $close_end + 1;
                $out .= substr($html, $i, $close_end - $i);
                $i = $close_end;
                continue;
            }

            $out .= $full_start;
            $i = $tag_end + 1;
        }

        return $out;
    }

    /**
     * Invoke the user callback for a start tag.
     *
     * @param string   $full
     * @param callable $callback
     * @return string
     */
    private static function apply_start_tag_callback($full, $callback) {
        $result = call_user_func($callback, array($full, self::extract_attributes($full)));
        return is_string($result) ? $result : $full;
    }

    /**
     * Invoke the user callback for a full element.
     *
     * @param string   $full
     * @param string   $attrs
     * @param string   $body
     * @param callable $callback
     * @return string
     */
    private static function apply_element_callback($full, $attrs, $body, $callback) {
        $result = call_user_func($callback, array($full, $attrs, $body));
        return is_string($result) ? $result : $full;
    }

    /**
     * Extract the raw attribute string from a start tag while preserving legacy callback shape.
     *
     * @param string $full Full start tag, including '<' and '>'.
     * @return string
     */
    private static function extract_attributes($full) {
        $inner = substr((string) $full, 1, -1);
        $sp = preg_match('/^[a-zA-Z][a-zA-Z0-9:-]*/', $inner, $nm) ? strlen($nm[0]) : 0;
        $attrs = substr($inner, $sp);
        if ('' !== $attrs && '/' === substr(rtrim($attrs), -1)) {
            $attrs = rtrim($attrs);
            $attrs = substr($attrs, 0, -1);
        }
        return $attrs;
    }

    /**
     * Find the matching close tag for a target element, counting nested same-name elements.
     *
     * @return array{0:int,1:int}|false Close tag start and position just after close tag.
     */
    private static function find_element_end($html, $tag, $start, $len) {
        $depth = 1;
        $i = $start;
        while ($i < $len) {
            $lt = strpos($html, '<', $i);
            if (false === $lt) {
                return false;
            }
            if (0 === substr_compare($html, '<!--', $lt, 4)) {
                $end = strpos($html, '-->', $lt + 4);
                $i = (false === $end) ? $len : $end + 3;
                continue;
            }
            if (0 === substr_compare($html, '<![CDATA[', $lt, 9)) {
                $end = strpos($html, ']]>', $lt + 9);
                $i = (false === $end) ? $len : $end + 3;
                continue;
            }
            if ($lt + 1 < $len && '!' === $html[$lt + 1]) {
                $end = strpos($html, '>', $lt + 1);
                $i = (false === $end) ? $len : $end + 1;
                continue;
            }
            if ($lt + 1 < $len && '/' === $html[$lt + 1]) {
                $name = self::read_tag_name($html, $lt + 2, $len);
                $end = strpos($html, '>', $lt + 1);
                if (false === $end) {
                    return false;
                }
                if (strtolower($name) === $tag) {
                    $depth--;
                    if (0 === $depth) {
                        return array($lt, $end + 1);
                    }
                }
                $i = $end + 1;
                continue;
            }

            $name = self::read_tag_name($html, $lt + 1, $len);
            $end = self::find_start_tag_end($html, $lt, $len);
            if (false === $end) {
                return false;
            }
            if (strtolower($name) === $tag) {
                $depth++;
            } elseif (isset(self::$raw_text_elements[strtolower($name)])) {
                $close = '</' . strtolower($name);
                $close_pos = stripos($html, $close, $end + 1);
                if (false === $close_pos) {
                    return false;
                }
                $close_end = strpos($html, '>', $close_pos);
                $i = (false === $close_end) ? $len : $close_end + 1;
                continue;
            }
            $i = $end + 1;
        }
        return false;
    }

    /**
     * Read an ASCII tag name starting at $start (the position just after '<').
     *
     * @return string Tag name, or '' if no valid name begins here.
     */
    private static function read_tag_name($html, $start, $len) {
        if ($start >= $len) {
            return '';
        }
        $c = $html[$start];
        if (!ctype_alpha($c)) {
            return '';
        }
        $name = $c;
        for ($j = $start + 1; $j < $len; $j++) {
            $c = $html[$j];
            if (ctype_alnum($c) || '-' === $c || ':' === $c) {
                $name .= $c;
            } else {
                break;
            }
        }
        return $name;
    }

    /**
     * Locate the '>' that closes the start tag beginning at $start, skipping any '>' that appears
     * inside a single- or double-quoted attribute value.
     *
     * @return int|false Index of the closing '>', or false if unterminated.
     */
    private static function find_start_tag_end($html, $start, $len) {
        $quote = '';
        for ($j = $start + 1; $j < $len; $j++) {
            $c = $html[$j];
            if ('' !== $quote) {
                if ($c === $quote) {
                    $quote = '';
                }
                continue;
            }
            if ('"' === $c || "'" === $c) {
                $quote = $c;
                continue;
            }
            if ('>' === $c) {
                return $j;
            }
        }
        return false;
    }
}
