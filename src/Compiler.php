<?php

namespace Drupal\scss_compiler;

use ScssPhp\ScssPhp\Compiler as ScssPhpCompiler;
use ScssPhp\ScssPhp\Type;
use ScssPhp\ScssPhp\Node\Number;

/**
 * Extends ScssPhp Compiler.
 *
 * Adds path variable to handle path to static resources relative to
 * theme/module.
 */
class Compiler extends ScssPhpCompiler {

  /**
   * Path to theme/module.
   *
   * @var string
   */
  public $drupalPath = '';

  /**
   * {@inheritdoc}
   */
  public function compileValue($value) {
    $value = $this->reduce($value);

    switch ($value[0]) {
      case Type::T_KEYWORD:
        return $value[1];

      case Type::T_COLOR:
        // [1] - red component (either number for a %)
        // [2] - green component
        // [3] - blue component
        // [4] - optional alpha component.
        list(, $r, $g, $b) = $value;

        $r = round($r);
        $g = round($g);
        $b = round($b);

        if (count($value) === 5 && $value[4] !== 1) {
          $a = new Number($value[4], '');

          return 'rgba(' . $r . ', ' . $g . ', ' . $b . ', ' . $a . ')';
        }

        $h = sprintf('#%02x%02x%02x', $r, $g, $b);

        // Converting hex color to short notation (e.g. #003399 to #039)
        if ($h[1] === $h[2] && $h[3] === $h[4] && $h[5] === $h[6]) {
          $h = '#' . $h[1] . $h[3] . $h[5];
        }

        return $h;

      case Type::T_NUMBER:
        return $value->output($this);

      case Type::T_STRING:
        return $value[1] . $this->compileStringContent($value) . $value[1];

      case Type::T_FUNCTION:
        $args = !empty($value[2]) ? $this->compileValue($value[2]) : '';

        if ($value[1] == 'url' && $args) {
          $args = trim($args, '"\'');
          if (substr($args, 0, 5) === 'data:') {
            return "$value[1](\"$args\")";
          }
          else {
            return "$value[1](\"$this->drupalPath$args\")";
          }
        }
        else {
          return "$value[1]($args)";
        }

      case Type::T_LIST:
        $value = $this->extractInterpolation($value);

        if ($value[0] !== Type::T_LIST) {
          return $this->compileValue($value);
        }

        list(, $delim, $items) = $value;

        if ($delim !== ' ') {
          $delim .= ' ';
        }

        $filtered = [];

        foreach ($items as $item) {
          if ($item[0] === Type::T_NULL) {
            continue;
          }

          $filtered[] = $this->compileValue($item);
        }

        return implode("$delim", $filtered);

      case Type::T_MAP:
        $keys = $value[1];
        $values = $value[2];
        $filtered = [];

        for ($i = 0, $s = count($keys); $i < $s; $i++) {
          $filtered[$this->compileValue($keys[$i])] = $this->compileValue($values[$i]);
        }

        array_walk($filtered, function (&$value, $key) {
          $value = $key . ': ' . $value;
        });

        return '(' . implode(', ', $filtered) . ')';

      case Type::T_INTERPOLATED:
        // Node created by extractInterpolation.
        list(, $interpolate, $left, $right) = $value;
        list(,, $whiteLeft, $whiteRight) = $interpolate;

        $left = count($left[2]) > 0 ?
          $this->compileValue($left) . $whiteLeft : '';

        $right = count($right[2]) > 0 ?
          $whiteRight . $this->compileValue($right) : '';

        return $left . $this->compileValue($interpolate) . $right;

      case Type::T_INTERPOLATE:
        // Strip quotes if it's a string.
        $reduced = $this->reduce($value[1]);

        switch ($reduced[0]) {
          case Type::T_LIST:
            $reduced = $this->extractInterpolation($reduced);

            if ($reduced[0] !== Type::T_LIST) {
              break;
            }

            list(, $delim, $items) = $reduced;

            if ($delim !== ' ') {
              $delim .= ' ';
            }

            $filtered = [];

            foreach ($items as $item) {
              if ($item[0] === Type::T_NULL) {
                continue;
              }

              $temp = $this->compileValue([Type::T_KEYWORD, $item]);
              if ($temp[0] === Type::T_STRING) {
                $filtered[] = $this->compileStringContent($temp);
              }
              elseif ($temp[0] === Type::T_KEYWORD) {
                $filtered[] = $temp[1];
              }
              else {
                $filtered[] = $this->compileValue($temp);
              }
            }

            $reduced = [Type::T_KEYWORD, implode("$delim", $filtered)];
            break;

          case Type::T_STRING:
            $reduced = [Type::T_KEYWORD, $this->compileStringContent($reduced)];
            break;

          case Type::T_NULL:
            $reduced = [Type::T_KEYWORD, ''];
        }

        return $this->compileValue($reduced);

      case Type::T_NULL:
        return 'null';

      default:
        $this->throwError("unknown value type: $value[0]");
    }
  }

}
