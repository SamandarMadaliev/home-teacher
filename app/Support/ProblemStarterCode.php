<?php

namespace App\Support;

/**
 * Default starter templates for problems. Unlike the playground's "Hello"
 * samples, these already read stdin, since every problem is stdin → stdout.
 */
class ProblemStarterCode
{
    public const TEMPLATES = [
        'javascript' => "const lines = require('fs').readFileSync(0, 'utf8').split('\\n');\n\n// Read input from `lines`, print the answer with console.log\n",
        'python' => "import sys\n\ndata = sys.stdin.read().split()\n\n# Read input from `data`, print the answer with print()\n",
        'php' => "<?php\n\n\$lines = explode(\"\\n\", trim(stream_get_contents(STDIN)));\n\n// Read input from \$lines, print the answer with echo\n",
        'go' => "package main\n\nimport (\n    \"bufio\"\n    \"fmt\"\n    \"os\"\n)\n\nfunc main() {\n    reader := bufio.NewReader(os.Stdin)\n    var n int\n    fmt.Fscan(reader, &n)\n    fmt.Println(n)\n}\n",
    ];

    public static function for(string $language): string
    {
        return self::TEMPLATES[$language] ?? '';
    }

    /**
     * Starter code per language for a problem: its own override if set,
     * otherwise the stdin template.
     *
     * @param  array<string, string>|null  $overrides
     * @return array<string, string>
     */
    public static function resolve(?array $overrides): array
    {
        $out = [];
        foreach (self::TEMPLATES as $lang => $template) {
            $custom = $overrides[$lang] ?? null;
            $out[$lang] = is_string($custom) && trim($custom) !== '' ? $custom : $template;
        }

        return $out;
    }
}
