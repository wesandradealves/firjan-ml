<?php

declare(strict_types=1);

namespace Drupal\eme\Utility;

/**
 * Export module file manipulation related utilities.
 *
 * @internal
 */
final class EmeModuleFileUtils {

  /**
   * Generates a hook implementation function name from a hook and module name.
   *
   * @param string $hook
   *   The name of the hook.
   * @param string $module_name
   *   The name of the module.
   *
   * @return string
   *   The name of the hook implementation function.
   */
  private static function getHookImplementationName(string $hook, string $module_name): string {
    return preg_replace('/^hook_(.*)$/', $module_name . '_${1}', $hook);
  }

  /**
   * Returns a pattern for the line of a hook implementation.
   *
   * @param string $hook
   *   The name of the hook.
   * @param string $module_name
   *   The name of the module.
   *
   * @return string
   *   The pattern for  of the hook implementation function.
   */
  private static function getHookPattern(string $hook, string $module_name): string {
    $implementation_quoted = preg_quote(self::getHookImplementationName($hook, $module_name), '/');
    return '/function\s+' . $implementation_quoted . '\(&?\s*(\$.+)\)\s*{/';
  }

  /**
   * Determines whether a hook is implemented in the module.
   *
   * @param string $hook
   *   The name of the hook to check for.
   * @param string $module_name
   *   The name of the module.
   * @param string $module_content
   *   The module's content where the hook usage should be checked.
   *
   * @return bool
   *   TRUE when the hook is implemented, FALSE otherwise.
   */
  public static function hookIsImplemented(string $hook, string $module_name, string $module_content): bool {
    return (bool) preg_match(self::getHookPattern($hook, $module_name), $module_content);
  }

  /**
   * Determines whether a class is declared as used inside the module.
   *
   * @param string $fqcn
   *   The fully qualified name of the class to use.
   * @param string $module_content
   *   The content of the module file.
   *
   * @return bool
   *   TRUE when the class is used, FALSE otherwise.
   */
  public static function classIsUsed(string $fqcn, string $module_content): bool {
    $class_name_qouted = preg_quote($fqcn, '/');
    $class_use_pattern = "/use\s+$class_name_qouted;/";
    return (bool) preg_match($class_use_pattern, $module_content);
  }

  /**
   * Determines whether the specified function is properly used in a module.
   *
   * @param string $hook
   *   The name of the hook.
   * @param string $function_template
   *   The function to check. When the function has to use the variables of the
   *   hook implementation, then the arg vars should be a dollar sign followed
   *   by the index of the hook variable arg. So 'functionName($1, $3)' for a
   *   hook 'hook_something(&$variable1, $variable2, $variable3)' will have
   *   valid usage when 'function($variable1, $variable3)' can be found inside
   *   the hook.
   * @param string $module_name
   *   The name of the module.
   * @param string $module_content
   *   The module's content where the function usage should be added.
   *
   * @return bool
   *   Whether the function is properly used inside the specified hook.
   */
  public static function functionUsedInHook(string $hook, string $function_template, string $module_name, string $module_content): bool {
    if (!self::hookIsImplemented($hook, $module_name, $module_content)) {
      throw new \LogicException();
    }

    $hook_pattern = self::getHookPattern($hook, $module_name);
    preg_match($hook_pattern, $module_content, $variable_matches);
    $variable_lits = array_filter(explode(',', $variable_matches[1]));
    $variables = array_reduce($variable_lits, function (array $carry, string $item) {
      if (preg_match('/\$[^\s]+/', $item, $matches)) {
        $carry[] = $matches[0];
      }
      return $carry;
    }, []);
    $search = array_reduce(array_keys($variables), function (array $carry, int $key) {
      $search = preg_quote('$' . ($key + 1), '/');
      $carry[] = "/$search/";
      return $carry;
    }, []);
    $function_to_check = preg_replace($search, $variables, $function_template);

    $hook_pattern_unquoted = trim($hook_pattern, '/');
    $function_pattern_unquoted = preg_quote($function_to_check, '/');

    $function_is_used = (bool) preg_match(
      "/{$hook_pattern_unquoted}[\s\S]*{$function_pattern_unquoted}/",
      $module_content,
      $matches
    );

    if (!$function_is_used) {
      return FALSE;
    }

    $function_has_no_args = !array_key_exists(2, $matches);
    $function_arg_is_in_hook_arg = strpos($matches[1], $matches[2] ?? $matches[1]) !== FALSE;

    return $function_is_used && ($function_has_no_args || $function_arg_is_in_hook_arg);
  }

  /**
   * Adds a 'use' declaration to the specified module file.
   *
   * @param string $fqcn
   *   The fully qualified name of the class to use.
   * @param string $module_content
   *   The content of the module file.
   */
  public static function addUseDeclaration(string $fqcn, string &$module_content): void {
    $file_head_pattern = '/(\<\?php\s*(?:\/\*[^\/]*@file[^\/]*\/)?)(\s*)((?:use[^;]*;)*)([\s\S]*)$/';
    preg_match($file_head_pattern, $module_content, $matches);
    $replacement = !empty($matches[3])
      ? <<<EOF
\${1}\${2}use $fqcn;
\${3}\${4}
EOF
      : <<<EOF
\${1}\${2}
use $fqcn;
\${3}\${4}
EOF;
    $module_content = preg_replace($file_head_pattern, $replacement, $module_content);
  }

  /**
   * Adds an empty hook implementation to the specified module content.
   *
   * @param string $hook
   *   The name of the hook.
   * @param string[] $hook_variable_literals
   *   The variables used by the hook implementation.
   * @param string $module_name
   *   The name of the module.
   * @param string $module_content
   *   The module's content where the function usage should be added.
   */
  public static function addEmptyHookImplementation(string $hook, array $hook_variable_literals, string $module_name, string &$module_content): void {
    $file_head_pattern = '/(\<\?php\s*(?:\/\*[^\/]*@file[^\/]*\/)?)(\s*)((?:use[^;]*;)*)([\s\S]*)$/';
    $hook_implementation_func_name = self::getHookImplementationName($hook, $module_name);
    $hook_variables = implode(', ', $hook_variable_literals);
    $replacement = <<<EOF
\${1}\${2}\${3}\${4}
/**
 * Implements $hook().
 */
function $hook_implementation_func_name($hook_variables) {
}

EOF;
    $module_content = preg_replace($file_head_pattern, $replacement, $module_content);
  }

  /**
   * Adds a function into the body of the specified hook implementation.
   *
   * @param string $hook
   *   The name of the hook.
   * @param string $function_template
   *   The function to add. When the function has to use the variables of the
   *   hook implementation, then the arg vars should be a dollar sign followed
   *   by the index of the hook variable arg. So 'functionName($1, $3)' used in
   *   a 'hook_something(&$variable1, $variable2, $variable3)' will be inserted
   *   as 'function($variable1, $variable3)'.
   * @param string $module_name
   *   The name of the module.
   * @param string $module_content
   *   The module's content where the function usage should be added.
   */
  public static function addFunctionToHook(string $hook, string $function_template, string $module_name, string &$module_content): void {
    if (!self::hookIsImplemented($hook, $module_name, $module_content)) {
      throw new \LogicException(sprintf("Hook '%s' is not implemented.", $hook));
    }

    $hook_pattern = self::getHookPattern($hook, $module_name);
    preg_match($hook_pattern, $module_content, $variable_matches);
    $variable_lits = array_filter(explode(',', $variable_matches[1]));
    $variables = array_reduce($variable_lits, function (array $carry, string $item) {
      if (preg_match('/\$[^\s]+/', $item, $matches)) {
        $carry[] = $matches[0];
      }
      return $carry;
    }, []);
    $search = array_reduce(array_keys($variables), function (array $carry, int $key) {
      $search = preg_quote('$' . ($key + 1), '/');
      $carry[] = "/$search/";
      return $carry;
    }, []);
    $function_to_add = preg_replace($search, $variables, $function_template);
    $replacement = <<<EOF
\${0}
  $function_to_add;
EOF;
    $module_content = preg_replace($hook_pattern, $replacement, $module_content);
  }

  /**
   * Adds a 'use' declaration to the specified module file if it is not there.
   *
   * @param string $fqcn
   *   The fully qualified name of the class to use.
   * @param string $module_content
   *   The content of the module file.
   */
  public static function ensureUseDeclaration(string $fqcn, string &$module_content): void {
    // If the class is already declared as 'used', there's nothing to do.
    if (self::classIsUsed($fqcn, $module_content)) {
      return;
    }

    self::addUseDeclaration($fqcn, $module_content);
  }

  /**
   * Adds a function to a hook implementation if it cannot be found.
   *
   * @param string $hook
   *   The name of the hook.
   * @param string[] $hook_variable_literals
   *   The variables used by the hook implementation.
   * @param string $function_template
   *   The function to add. When the function has to use the variables of the
   *   hook implementation, then the arg vars should be a dollar sign followed
   *   by the index of the hook variable arg. So 'functionName($1, $3)' used in
   *   a 'hook_something(&$variable1, $variable2, $variable3)' will be inserted
   *   as 'function($variable1, $variable3)'.
   * @param string $module_name
   *   The name of the module.
   * @param string $module_content
   *   The module's content where the function usage should be added.
   */
  public static function ensureFunctionUsedInHook(string $hook, array $hook_variable_literals, string $function_template, string $module_name, string &$module_content): void {
    if (!self::hookIsImplemented($hook, $module_name, $module_content)) {
      self::addEmptyHookImplementation($hook, $hook_variable_literals, $module_name, $module_content);
    }

    if (!self::functionUsedInHook($hook, $function_template, $module_name, $module_content)) {
      self::addFunctionToHook($hook, $function_template, $module_name, $module_content);
    }
  }

  /**
   * Returns an empty module file with file comment.
   *
   * @param string $module_name
   *   The name of the module.
   *
   * @return string
   *   A string representing an empty module file.
   */
  public static function getBareModuleFile(string $module_name):string {
    return <<<EOF
<?php

/**
 * @file
 * Implemented hooks for $module_name.
 */

EOF;
  }

  /**
   * Returns the content of the module implements alterer class.
   *
   * @param string $module_name
   *   The name of the export module.
   *
   * @return string
   *   The content of the module implements alterer class.
   */
  public static function moduleImplementsAltererClass(string $module_name): string {
    return <<<EOF
<?php

namespace Drupal\\$module_name;

/**
 * Alters the module's hook implementations.
 */
class ModuleImplementsAlterer {

  /**
   * Suppresses content moderation entity save hooks.
   */
  public static function alter(&\$implementations, \$hook) {
    if (in_array(\$hook, ['entity_update', 'entity_presave', 'entity_insert'])) {
      unset(\$implementations['content_moderation']);
    }
  }

}

EOF;
  }

}
