<?php namespace ChrisKonnertz\BBCode;

/*
 * BBCode to HTML converter
 *
 * Inspired by Kai Mallea (kmallea@gmail.com)
 *
 * Licensed under the MIT license: 
 * http://www.opensource.org/licenses/mit-license.php
 */

use ChrisKonnertz\BBCode\Tag;

class BBCode {

    /**
     * The text with BBCodes
     * @var string
     */
    protected $text = null;

    /**
     * The constructor creates functions for all replacements.
     */
    public function __construct($text = null) 
    {
        $this->text = $text;
    }

    /**
     * Renders BBCode to HMTL
     * 
     * @param  string  $text    The BBCode string
     * @param  bool    $escape  Escape HTML entities? (Only "<" and ">"!)
     * @return string
     */
    public function render($text = null, $escape = true) 
    {
        if ($this->text !== null and $text === null) {
            $text = $this->text;
        }
        
        $html   = '';
        $len    = strlen($text);
        $inTag  = false;            // True if current position is inside a tag
        $inName = false;            // True if current pos is inside a tag name
        $inStr  = false;            // True if current pos is inside a string
        $tag    = null;
        $tags   = array();

        /*
         * Loop over each character of the text
         */
        for ($i = 0; $i < $len; $i++) {
            $char = $text[$i];

            if (! $escape or ($char != '<' and $char != '>')) {
                /*
                 * $inTag == true means the curren position is inside a tag
                 * (= inside the brackets)
                 */
                if ($inTag) {
                    if ($char == '"') {
                        if ($inStr) {
                            $inStr = false;
                        } else {
                            if ($inName) {
                                $tag->valid = false;
                            } else {
                                $inStr = true;
                            }
                        }
                    } else {
                        /*
                         * This closes a tag
                         */
                        if ($char == ']' and ! $inStr) {
                            $inTag  = false;
                            $inName = false;

                            if ($tag->valid) {
                                $code = null;
                                if ($tag->opening) {
                                    $code = $this->generateTag($tag, $html);
                                } else {
                                    $openingTag = $this->popTag($tags, $tag);
                                    if ($openingTag) {
                                        $code = $this->generateTag($tag, $html, $openingTag);
                                    }
                                }                       

                                if ($code !== null and $tag->opening) { 
                                    $tags[$tag->name][] = $tag;
                                }
                                $html .= $code;
                            }
                            continue;
                        }

                        if ($inName and ! $inStr) {
                            /*
                             * This makes the current tag a closing tag
                             */
                            if ($char == '/') {
                                if ($tag->name) {
                                    $tag->valid = false;
                                } else {
                                    $tag->opening = false;
                                }
                            } else {
                                /*
                                 * This means a property starts
                                 */
                                if ($char == '=') {
                                    if ($tag->name) {
                                        $inName = false;
                                    } else {
                                        $tag->valid = false;
                                    }
                                } else {
                                    $tag->name .= strtolower($char);    
                                }
                            }
                        } else { // If we are not inside the name we are inside a property
                            $tag->property .= $char;
                        }
                    } 
                } else {
                    /*
                     * This opens a tag
                     */
                    if ($char == '[') {
                        $inTag          = true;
                        $inName         = true;
                        $tag            = new Tag();
                        $tag->position  = strlen($html);
                    } else {
                        $html .= $char;
                    }
                }
            } else {
                $html .= htmlspecialchars($char);
            }
        }

        /*
         * Check for tags that are not closed and close them.
         */
        foreach ($tags as $name => $tagsCollection) {
            $closingTag = new Tag($name, false);
            foreach ($tagsCollection as $tag) {
                $html .= $this->generateTag($closingTag, $html, $tag);
            }
        }

        return $html;
    }

    /**
     * Generate the HTML code of the current tag
     * 
     * @param  Tag      $tag        The tag
     * @param  Tag      $openingTag The opening tag that is linked to the tag (or null)
     * @return string
     */
    protected function generateTag(Tag $tag, &$html, Tag $openingTag = null)
    {
        $code = null;

        switch ($tag->name) {
            case 'b':
                if ($tag->opening) {
                    $code = '<strong>';
                } else {
                    $code = '</strong>';
                }
                break;
            case 'i':
                if ($tag->opening) {
                    $code = '<em>';
                } else {
                    $code = '</em>';
                }
                break;
            case 's':
                if ($tag->opening) {
                    $code = '<del>';
                } else {
                    $code = '</del>';
                }
                break;
            case 'u':
                if ($tag->opening) {
                    $code = '<span style="text-decoration: underline">';
                } else {
                    $code = '</span>';
                }
                break;
            case 'code':
                if ($tag->opening) {
                    $code = '<pre><code>';
                } else {
                    $code = '</pre></code>';
                }
                break;
            case 'email':
                if ($tag->opening) {
                    if ($tag->property) {
                        $code = '<a href="mailto:'.$tag->property.'">';
                    } else {
                        $code = '<a href="mailto:';
                    }
                } else {
                    if ($openingTag->property) {
                        $code = '</a>';
                    } else {
                        $code .= '">'.substr($html, $openingTag->position + 16).'</a>';
                    }
                }
                break;
            case 'url':
                if ($tag->opening) {
                    if ($tag->property) {
                        $code = '<a href="'.$tag->property.'">';
                    } else {
                        $code = '<a href="';
                    }
                } else {
                    if ($openingTag->property) {
                        $code = '</a>';
                    } else {
                        $partial = substr($html, $openingTag->position + 9);
                        $html = substr($html, 0, $openingTag->position + 9)
                            .strip_tags($partial).'">'.$partial.'</a>';
                    }
                }
                break;
            case 'img':
                if ($tag->opening) {
                    $code = '<img src="';
                } else {
                    $code = '" />';
                }
                break;
            case 'list':
                if ($tag->opening) {
                    $listType = '<ul>';

                    if ($tag->property) {
                        $listType = '<ol>';
                        if ($tag->property == 'a') {
                            $listType = '<ol style="list-style-type: lower-alpha">';
                        }
                    }

                    $code = $listType;
                } else {
                    if ($this->endsWith($html, '<ul>')) {
                        $code = '</ul>';
                    } elseif ($this->endsWith($html, '<ol>')) {
                        $code = '</ol>';
                    } elseif ($this->endsWith($html, '<ol style="list-style-type: lower-alpha">')) {
                        $code = '</ol>';
                    } elseif ($this->endsWith($html, '</li>') and $openingTag->property) {
                        $code = '</ol>';
                    } elseif ($this->endsWith($html, '</li>') and ! $openingTag->property) {
                        $code = '</ul>';
                    } elseif ($openingTag->property) {
                        $code = '</li></ol>';
                    } else {
                        $code = '</li></ul>';
                    }
                }
                break;
            case '*':
                if ($tag->opening) {
                    $tag->opening = false;
                    if ($this->endsWith($html, '<ul>')) {
                        $code = '<li>';
                    } else {
                        $code = '</li><li>';
                    }
                }
                break;
            case 'li':
                if ($tag->opening) {
                    $code = '<li>';
                } else {
                    $code = '</li>';
                }
                break;
            case 'quote':
                if ($tag->opening) {
                    if ($tag->property) {
                        $code = '<blockquote><span class="author">'.$tag->property.':</span><br/>';
                    } else {
                        $code = '<blockquote>';
                    }
                } else {
                    $code = '</blockquote>';
                }
                break;
            case 'youtube':
                if ($tag->opening) {
                    $code = '<iframe class="youtube-player" type="text/html" width="640"\
                        height="385" src="http://www.youtube.com/embed/';
                } else {
                    $code = '" frameborder="0"></iframe>';
                }
                break;
            case 'font':
                if ($tag->opening) {
                    if ($tag->property) {
                        $code = '<span style="font-family: '.$tag->property.'">';
                    }
                } else {
                    $code = '</span>';
                }
                break;
            case 'size':
                if ($tag->opening) {
                    if ($tag->property) {
                        $code = '<span style="font-size: '.$tag->property.'%">';
                    }
                } else {
                    $code = '</span>';
                }
                break;
            case 'color':
                if ($tag->opening) {
                    if ($tag->property) {
                        $code = '<span style="color: '.$tag->property.'">';
                    }
                } else {
                    $code = '</span>';
                }
                break;
            case 'center':
                if ($tag->opening) {
                    $code = '<div style="text-align: center">';
                } else {
                    $code = '</div>';
                }
                break;
        }

        return $code;
    }

    /**
     * Returns the last tag of a given type and removes it from the array.
     * 
     * @param  array    $tags Array of tags
     * @param  Tag      $tag  Return the last tag of the type of this tag
     * @return Tag
     */
    protected function popTag(array &$tags, $tag)
    {
        if (! isset($tags[$tag->name])) return null;

        $size = sizeof($tags[$tag->name]);

        if ($size == 0) {
            return null;
        } else {
            return array_pop($tags[$tag->name]);
        }
    }

    /**
     * Returns true if $haystack ends with $needle
     * 
     * @param  string $haystack
     * @param  string $needle
     * @return bool
     */
    protected function endsWith($haystack, $needle)
    {
        return $needle === "" || substr($haystack, -strlen($needle)) === $needle;
    }
}