--TEST--
pasted text: the first short line is the title, ``` fences are code, wrapped lines are joined
--FILE--
<?php
require __DIR__ . '/setup.inc';
$text = <<<TEXT
# Faster imports

The import took four hours because every row ran
a lookup on an unindexed column. See https://example.com/explain for the plan.

```sql
SELECT 1;
```

- We added an index on the column and it took 48 minutes.
TEXT;
$article = Article_reader::from_text($text);
var_dump($article['title'], $article['code_blocks'], $article['links']);
print_r($article['sentences']);
?>
--EXPECT--
string(14) "Faster imports"
int(1)
int(1)
Array
(
    [0] => The import took four hours because every row ran a lookup on an unindexed column.
    [1] => See https://example.com/explain for the plan.
    [2] => We added an index on the column and it took 48 minutes.
)
