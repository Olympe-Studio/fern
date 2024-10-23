<?php

// .php-cs-fixer.dist.php

$finder = PhpCsFixer\Finder::create()
  ->in([
    __DIR__ . '/src',
    __DIR__ . '/tests',
  ])
  ->name('*.php')
  ->notName('*.blade.php')
  ->ignoreDotFiles(true)
  ->ignoreVCS(true);

return (new PhpCsFixer\Config())
  ->setRules([
    // PHP 7.4+ features
    'declare_strict_types' => true,
    'strict_param' => true,
    'array_syntax' => ['syntax' => 'short'],

    // Namespaces
    'ordered_imports' => [
      'sort_algorithm' => 'alpha',
      'imports_order' => ['class', 'function', 'const'],
    ],
    'no_unused_imports' => true,
    'global_namespace_import' => [
      'import_classes' => true,
      'import_constants' => true,
      'import_functions' => true,
    ],

    // Spacing
    'no_extra_blank_lines' => [
      'tokens' => [
        'extra',
        'throw',
        'use',
        'use_trait',
        'curly_brace_block',
      ]
    ],
    'blank_line_before_statement' => [
      'statements' => [
        'return',
        'throw',
        'try',
        'if',
        'while',
        'for',
        'foreach',
        'do',
        'switch',
        'case',
      ]
    ],
    'method_chaining_indentation' => true,
    'no_spaces_around_offset' => true,

    // Docblocks and Comments
    'phpdoc_align' => [
      'align' => 'vertical',
      'tags' => ['param', 'return', 'throws', 'type', 'var']
    ],
    'phpdoc_separation' => true,
    'phpdoc_trim' => true,
    'phpdoc_types_order' => [
      'null_adjustment' => 'always_last',
      'sort_algorithm' => 'none',
    ],
    'phpdoc_var_without_name' => true,
    'align_multiline_comment' => [
      'comment_type' => 'phpdocs_only'
    ],

    // Braces and Operators
    'operator_linebreak' => [
      'only_booleans' => true,
      'position' => 'beginning'
    ],
    'standardize_not_equals' => true,
    'ternary_operator_spaces' => true,

    // Type Declarations
    'fully_qualified_strict_types' => true,
    'no_superfluous_phpdoc_tags' => [
      'allow_mixed' => true,
      'remove_inheritdoc' => false,
    ],

    // Code Style
    'single_quote' => true,
    'trailing_comma_in_multiline' => [
      'elements' => ['arrays', 'arguments', 'parameters'],
    ],
    'no_alternative_syntax' => true,
    'simplified_if_return' => true,
    'explicit_string_variable' => true,

    // Function Declarations
    'function_typehint_space' => true,
    'method_argument_space' => [
      'on_multiline' => 'ensure_fully_multiline',
      'keep_multiple_spaces_after_comma' => false,
    ],
    'nullable_type_declaration_for_default_null_value' => [
      'use_nullable_type_declaration' => true,
    ],

    // Class Notation
    'class_attributes_separation' => [
      'elements' => [
        'const' => 'one',
        'method' => 'one',
        'property' => 'one',
      ],
    ],
    'ordered_class_elements' => [
      'order' => [
        'use_trait',
        'case',
        'constant_public',
        'constant_protected',
        'constant_private',
        'property_public',
        'property_protected',
        'property_private',
        'construct',
        'destruct',
        'magic',
        'phpunit',
        'method_public',
        'method_protected',
        'method_private',
      ],
    ],

    // Control Structures
    'no_unneeded_control_parentheses' => [
      'statements' => ['break', 'clone', 'continue', 'echo_print', 'return', 'switch_case', 'yield'],
    ],
    'no_unneeded_curly_braces' => [
      'namespaces' => true,
    ],
  ])
  ->setRiskyAllowed(true)
  ->setFinder($finder);
