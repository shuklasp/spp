<?php
namespace SPPMod\SPPOS;

/**
 * Class SqlLexer
 * 
 * A custom PHP SQL Lexer and AST Parser to safely parse and reconstruct SQL queries
 * without relying on brittle regular expressions. Used heavily by VirtualPDO.
 */
class SqlLexer
{
    /**
     * Tokenizes a raw SQL string into an array of tokens.
     */
    public static function tokenize(string $sql): array
    {
        $tokens = [];
        $length = strlen($sql);
        $i = 0;

        while ($i < $length) {
            $char = $sql[$i];

            // 1. Whitespace
            if (ctype_space($char)) {
                $whitespace = '';
                while ($i < $length && ctype_space($sql[$i])) {
                    $whitespace .= $sql[$i];
                    $i++;
                }
                $tokens[] = ['type' => 'WHITESPACE', 'value' => $whitespace];
                continue;
            }

            // 2. Strings (Single quotes)
            if ($char === "'") {
                $str = "'";
                $i++;
                while ($i < $length) {
                    $str .= $sql[$i];
                    if ($sql[$i] === "'" && ($i + 1 >= $length || $sql[$i + 1] !== "'")) {
                        $i++;
                        break;
                    }
                    if ($sql[$i] === "'" && $i + 1 < $length && $sql[$i + 1] === "'") {
                        $str .= "'"; // escaped quote
                        $i += 2;
                        continue;
                    }
                    $i++;
                }
                $tokens[] = ['type' => 'STRING', 'value' => $str];
                continue;
            }

            // 3. Strings (Double quotes)
            if ($char === '"') {
                $str = '"';
                $i++;
                while ($i < $length) {
                    $str .= $sql[$i];
                    if ($sql[$i] === '"' && ($i + 1 >= $length || $sql[$i + 1] !== '"')) {
                        $i++;
                        break;
                    }
                    if ($sql[$i] === '"' && $i + 1 < $length && $sql[$i + 1] === '"') {
                        $str .= '"'; // escaped quote
                        $i += 2;
                        continue;
                    }
                    $i++;
                }
                $tokens[] = ['type' => 'STRING', 'value' => $str];
                continue;
            }

            // 4. Identifiers / Keywords
            if (ctype_alnum($char) || $char === '_' || $char === '`') {
                $ident = '';
                while ($i < $length && (ctype_alnum($sql[$i]) || $sql[$i] === '_' || $sql[$i] === '`' || $sql[$i] === '.')) {
                    $ident .= $sql[$i];
                    $i++;
                }
                $upper = strtoupper($ident);
                if (in_array($upper, ['UPDATE', 'SET', 'SELECT', 'FROM', 'INSERT', 'INTO', 'VALUES', 'WHERE', 'AND', 'OR'])) {
                    $tokens[] = ['type' => 'KEYWORD', 'value' => $upper, 'original' => $ident];
                } else {
                    $tokens[] = ['type' => 'IDENTIFIER', 'value' => $ident];
                }
                continue;
            }

            // 5. Operators & Punctuation
            if (in_array($char, ['=', ',', '(', ')', ';', '*', '<', '>'])) {
                $tokens[] = ['type' => 'OPERATOR', 'value' => $char];
                $i++;
                continue;
            }

            // Unknown
            $tokens[] = ['type' => 'UNKNOWN', 'value' => $char];
            $i++;
        }

        return $tokens;
    }

    /**
     * Finds string literal assignments (e.g. `value` = 'sk_test_123') 
     * inside the AST and replaces the string value if a callback matches.
     */
    public static function interceptStringAssignments(string $sql, callable $callback): string
    {
        $tokens = self::tokenize($sql);
        $reconstructed = '';
        $tokenCount = count($tokens);

        for ($i = 0; $i < $tokenCount; $i++) {
            $token = $tokens[$i];

            // If we find an identifier followed by an '=' and then a string...
            // Note: In real life we'd build a full tree. For our specific Vault use-case,
            // we scan the token stream for: IDENTIFIER (optional whitespace) OPERATOR(=) (optional whitespace) STRING
            if ($token['type'] === 'IDENTIFIER') {
                $j = $i + 1;
                
                // Skip whitespace
                while ($j < $tokenCount && $tokens[$j]['type'] === 'WHITESPACE') { $j++; }
                
                if ($j < $tokenCount && $tokens[$j]['type'] === 'OPERATOR' && $tokens[$j]['value'] === '=') {
                    $k = $j + 1;
                    
                    // Skip whitespace
                    while ($k < $tokenCount && $tokens[$k]['type'] === 'WHITESPACE') { $k++; }

                    if ($k < $tokenCount && $tokens[$k]['type'] === 'STRING') {
                        // We found an assignment! e.g., column = 'value'
                        $columnName = trim($token['value'], "`");
                        
                        // Extract actual string inside quotes
                        $quoteChar = $tokens[$k]['value'][0];
                        $rawValue = substr($tokens[$k]['value'], 1, -1);
                        
                        // Pass to callback
                        $newValue = $callback($columnName, $rawValue);
                        
                        if ($newValue !== $rawValue) {
                            $tokens[$k]['value'] = $quoteChar . $newValue . $quoteChar;
                        }
                    }
                }
            }

            // Reconstruct SQL
            if (isset($token['original'])) {
                $reconstructed .= $token['original'];
            } else {
                $reconstructed .= $token['value'];
            }
        }

        return $reconstructed;
    }
}
