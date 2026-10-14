-- =====================================================================
--  Code Lab (learning paths) : schema + A/L ICT starter content
--  Run this once in phpMyAdmin (or:  mysql -u root -p your_db < learning_tool.sql)
--  Safe to re-run: tables use IF NOT EXISTS and seed rows use INSERT IGNORE.
-- =====================================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS lp_modules (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  title         VARCHAR(150) NOT NULL,
  description   VARCHAR(300) NOT NULL DEFAULT '',
  unit_label    VARCHAR(120) NOT NULL DEFAULT '',
  icon          VARCHAR(16)  NOT NULL DEFAULT '💻',
  sort_order    INT NOT NULL DEFAULT 0,
  is_published  TINYINT(1) NOT NULL DEFAULT 1,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lp_tasks (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  module_id     INT NOT NULL,
  title         VARCHAR(150) NOT NULL,
  summary       VARCHAR(255) NOT NULL DEFAULT '',
  icon          VARCHAR(16)  NOT NULL DEFAULT '💻',
  language      ENUM('html','css','php','python') NOT NULL DEFAULT 'html',
  instructions  MEDIUMTEXT,
  starter_code  MEDIUMTEXT,
  hint          TEXT,
  checks        MEDIUMTEXT,
  points        INT NOT NULL DEFAULT 10,
  sort_order    INT NOT NULL DEFAULT 0,
  is_project    TINYINT(1) NOT NULL DEFAULT 0,
  is_published  TINYINT(1) NOT NULL DEFAULT 1,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_module (module_id, sort_order),
  CONSTRAINT fk_lp_tasks_module FOREIGN KEY (module_id) REFERENCES lp_modules(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lp_progress (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  user_id        INT NOT NULL,
  task_id        INT NOT NULL,
  status         ENUM('started','completed') NOT NULL DEFAULT 'started',
  code           MEDIUMTEXT,
  points_awarded INT NOT NULL DEFAULT 0,
  attempts       INT NOT NULL DEFAULT 0,
  last_activity  DATETIME NULL,
  completed_at   DATETIME NULL,
  UNIQUE KEY uq_user_task (user_id, task_id),
  KEY idx_user_done (user_id, status, completed_at),
  CONSTRAINT fk_lp_progress_task FOREIGN KEY (task_id) REFERENCES lp_tasks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lp_saved (
  user_id    INT NOT NULL,
  module_id  INT NOT NULL,
  saved_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, module_id),
  CONSTRAINT fk_lp_saved_module FOREIGN KEY (module_id) REFERENCES lp_modules(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lp_settings (
  setting_key   VARCHAR(50) PRIMARY KEY,
  setting_value VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO lp_settings (setting_key, setting_value) VALUES
  ('weekly_points_goal', '100'),
  ('weekly_tasks_goal', '5'),
  ('streak_goal', '2');

-- ---------- Starter modules ----------
INSERT IGNORE INTO lp_modules (id, title, description, unit_label, icon, sort_order, is_published) VALUES (1, 'HTML Basics', 'Build your first web pages with HTML5 tags.', 'A/L ICT · Unit 10 · Web Development', '🌐', 1, 1);
INSERT IGNORE INTO lp_modules (id, title, description, unit_label, icon, sort_order, is_published) VALUES (2, 'CSS Styling', 'Make pages look good with colours, fonts and spacing.', 'A/L ICT · Unit 10 · Web Development', '🎨', 2, 1);
INSERT IGNORE INTO lp_modules (id, title, description, unit_label, icon, sort_order, is_published) VALUES (3, 'Python Programming', 'Write real Python that runs right in your browser.', 'A/L ICT · Unit 9 · Programming', '🐍', 3, 1);
INSERT IGNORE INTO lp_modules (id, title, description, unit_label, icon, sort_order, is_published) VALUES (4, 'PHP Basics', 'Server-side scripting: echo, forms and MySQL.', 'A/L ICT · Unit 10 · Web Development', '🐘', 4, 1);
INSERT IGNORE INTO lp_modules (id, title, description, unit_label, icon, sort_order, is_published) VALUES (5, 'Syllabus Projects', 'Bigger builds that bring everything together.', 'A/L ICT · Unit 14 · Project', '🏆', 5, 1);

-- ---------- Starter tasks ----------
INSERT IGNORE INTO lp_tasks (id, module_id, sort_order, title, summary, icon, language, points, is_project, instructions, starter_code, hint, checks, is_published) VALUES
  (101, 1, 1, 'Your first web page', 'Page skeleton, title and heading', '📄', 'html', 10, 0, 'Every web page follows the same skeleton: a `<!DOCTYPE html>` line, then `<html>` containing a `<head>` and a `<body>`.

- The `<title>` goes inside `<head>` and shows on the browser tab.
- Headings such as `<h1>` go inside `<body>` and show on the page.

**Your job:** add a title and a main heading to this page. The live preview updates as you type.', '<!DOCTYPE html>
<html>
<head>
  <!-- add a <title> here -->
</head>
<body>
  <!-- add an <h1> heading here -->
</body>
</html>
', 'Write `<title>My first page</title>` inside the head and `<h1>Hello, world</h1>` inside the body.', '[{"type": "code_contains", "selector": "", "value": "<!DOCTYPE html>", "extra": "", "message": "The page starts with <!DOCTYPE html>"}, {"type": "dom_text_contains", "selector": "title", "value": "", "extra": "", "message": "Add a <title> with some text inside <head>"}, {"type": "code_regex", "selector": "", "value": "<body[^>]*>[\\\\s\\\\S]*<h1", "extra": "", "message": "The <h1> is written inside <body>"}, {"type": "dom_text_contains", "selector": "body h1", "value": "", "extra": "", "message": "The <h1> heading has some text"}]', 1);

INSERT IGNORE INTO lp_tasks (id, module_id, sort_order, title, summary, icon, language, points, is_project, instructions, starter_code, hint, checks, is_published) VALUES
  (102, 1, 2, 'Format your text', 'Paragraphs, bold, italic, line breaks', '📝', 'html', 10, 0, 'Text tags give a page its structure:

- `<p>` makes a paragraph
- `<strong>` makes text bold and `<em>` makes it italic
- `<br>` starts a new line inside a paragraph

**Your job:** write a short "About me" section with at least two paragraphs. Make one word bold, one word italic and use one line break.', '<!DOCTYPE html>
<html>
<head>
  <title>About me</title>
</head>
<body>
  <h1>About me</h1>
  <!-- add your paragraphs here -->
</body>
</html>
', 'Example: `<p>I study <strong>ICT</strong> and I love <em>coding</em>.<br>Nice to meet you!</p>`', '[{"type": "dom_count_min", "selector": "body p", "value": "2", "extra": "", "message": "At least two <p> paragraphs"}, {"type": "dom_exists", "selector": "strong", "value": "", "extra": "", "message": "One word is bold with <strong>"}, {"type": "dom_exists", "selector": "em", "value": "", "extra": "", "message": "One word is italic with <em>"}, {"type": "dom_exists", "selector": "br", "value": "", "extra": "", "message": "One line break with <br>"}]', 1);

INSERT IGNORE INTO lp_tasks (id, module_id, sort_order, title, summary, icon, language, points, is_project, instructions, starter_code, hint, checks, is_published) VALUES
  (103, 1, 3, 'Lists', 'Ordered and unordered lists', '📋', 'html', 10, 0, 'HTML has two main list types:

- `<ul>` is an unordered (bulleted) list
- `<ol>` is an ordered (numbered) list
- each item inside either one is an `<li>`

**Your job:** list three computer hardware devices in a `<ul>`, then write three steps to start a computer in an `<ol>`.', '<!DOCTYPE html>
<html>
<head>
  <title>Lists</title>
</head>
<body>
  <h2>Hardware devices</h2>
  <!-- unordered list here -->

  <h2>Starting a computer</h2>
  <!-- ordered list here -->
</body>
</html>
', 'Each list looks like `<ul>` then three `<li>...</li>` lines, then `</ul>`.', '[{"type": "dom_count_min", "selector": "ul li", "value": "3", "extra": "", "message": "An unordered list with 3 items"}, {"type": "dom_count_min", "selector": "ol li", "value": "3", "extra": "", "message": "An ordered list with 3 items"}]', 1);

INSERT IGNORE INTO lp_tasks (id, module_id, sort_order, title, summary, icon, language, points, is_project, instructions, starter_code, hint, checks, is_published) VALUES
  (104, 1, 4, 'Build a table', 'Rows, headers and cells', '🗂️', 'html', 15, 0, 'Tables are built from rows and cells:

- `<table>` wraps everything
- `<tr>` is a row
- `<th>` is a header cell and `<td>` is a normal cell

**Your job:** make a marks table with the columns Subject, Marks and Grade, plus three subject rows.', '<!DOCTYPE html>
<html>
<head>
  <title>Marks table</title>
</head>
<body>
  <h1>My marks</h1>
  <!-- build the table here -->
</body>
</html>
', 'You need 4 rows in total: one header row with three `<th>` cells and three rows with three `<td>` cells each.', '[{"type": "dom_exists", "selector": "table", "value": "", "extra": "", "message": "The page has a <table>"}, {"type": "dom_count_min", "selector": "table th", "value": "3", "extra": "", "message": "Three header cells: Subject, Marks, Grade"}, {"type": "dom_count_min", "selector": "table tr", "value": "4", "extra": "", "message": "Four rows: 1 header + 3 subjects"}, {"type": "dom_count_min", "selector": "table td", "value": "9", "extra": "", "message": "Nine data cells (3 rows x 3 columns)"}]', 1);

INSERT IGNORE INTO lp_tasks (id, module_id, sort_order, title, summary, icon, language, points, is_project, instructions, starter_code, hint, checks, is_published) VALUES
  (201, 2, 1, 'Colour the heading', 'Your first CSS rule', '🖌️', 'css', 10, 0, 'CSS rules have a selector and declarations:

```
h1 {
  color: red;
}
```

**Your job:** make the `<h1>` heading the colour `tomato`.', '<!DOCTYPE html>
<html>
<head>
  <style>
    h1 {
      /* set the colour to tomato */
    }
  </style>
</head>
<body>
  <h1>Colours in CSS</h1>
</body>
</html>
', 'Add the line `color: tomato;` inside the curly brackets.', '[{"type": "dom_style", "selector": "h1", "value": "tomato", "extra": "color", "message": "The h1 colour is tomato"}]', 1);

INSERT IGNORE INTO lp_tasks (id, module_id, sort_order, title, summary, icon, language, points, is_project, instructions, starter_code, hint, checks, is_published) VALUES
  (202, 2, 2, 'Fonts and alignment', 'font-size and text-align', '🔤', 'css', 10, 0, 'Two very common properties:

- `font-size` changes how big text is (use px, for example `18px`)
- `text-align` moves text left, right or to the `center`

**Your job:** centre the heading, then make every paragraph 18px and centred.', '<!DOCTYPE html>
<html>
<head>
  <style>
    h1 {
      /* centre me */
    }
    p {
      /* 18px and centred */
    }
  </style>
</head>
<body>
  <h1>School news</h1>
  <p>Our ICT club meets every Friday.</p>
  <p>Bring a laptop if you have one.</p>
</body>
</html>
', 'Use `text-align: center;` and `font-size: 18px;`.', '[{"type": "dom_style", "selector": "h1", "value": "center", "extra": "text-align", "message": "The h1 is centred"}, {"type": "dom_style", "selector": "p", "value": "center", "extra": "text-align", "message": "Paragraphs are centred"}, {"type": "dom_style", "selector": "p", "value": "18px", "extra": "font-size", "message": "Paragraph font size is 18px"}]', 1);

INSERT IGNORE INTO lp_tasks (id, module_id, sort_order, title, summary, icon, language, points, is_project, instructions, starter_code, hint, checks, is_published) VALUES
  (203, 2, 3, 'The box model', 'Padding, border and background', '📦', 'css', 15, 0, 'Every element is a box: content, padding, border and margin.

**Your job:** style the `.card` box so it has:

- 20px of padding at the top
- a solid border
- a light blue background (`#e0f2fe`)
- rounded corners of 12px', '<!DOCTYPE html>
<html>
<head>
  <style>
    .card {
      /* style the card here */
    }
  </style>
</head>
<body>
  <div class="card">
    <h2>ICT Club</h2>
    <p>Learn to code every Friday.</p>
  </div>
</body>
</html>
', 'Try `padding: 20px; border: 2px solid navy; background-color: #e0f2fe; border-radius: 12px;`', '[{"type": "dom_style", "selector": ".card", "value": "20px", "extra": "padding-top", "message": "Padding is 20px"}, {"type": "dom_style", "selector": ".card", "value": "solid", "extra": "border-top-style", "message": "The border is solid"}, {"type": "dom_style", "selector": ".card", "value": "#e0f2fe", "extra": "background-color", "message": "Background colour is #e0f2fe"}, {"type": "dom_style", "selector": ".card", "value": "12px", "extra": "border-top-left-radius", "message": "Corners are rounded by 12px"}]', 1);

INSERT IGNORE INTO lp_tasks (id, module_id, sort_order, title, summary, icon, language, points, is_project, instructions, starter_code, hint, checks, is_published) VALUES
  (204, 2, 4, 'Style a table', 'border-collapse, header colours', '📊', 'css', 15, 0, 'Plain tables look cramped. CSS fixes that:

- `border-collapse: collapse;` merges double borders
- `padding` gives cells breathing room

**Your job:** collapse the table borders, give header cells a dark background (`#1e293b`) with white text, and add 10px of padding to data cells.', '<!DOCTYPE html>
<html>
<head>
  <style>
    table { }
    th { }
    td { }
  </style>
</head>
<body>
  <table border="1">
    <tr><th>Subject</th><th>Marks</th></tr>
    <tr><td>ICT</td><td>88</td></tr>
    <tr><td>SFT</td><td>76</td></tr>
  </table>
</body>
</html>
', 'Put `border-collapse: collapse;` in `table`, `background-color` and `color` in `th`, and `padding: 10px;` in `td`.', '[{"type": "dom_style", "selector": "table", "value": "collapse", "extra": "border-collapse", "message": "Table borders are collapsed"}, {"type": "dom_style", "selector": "th", "value": "#1e293b", "extra": "background-color", "message": "Header background is #1e293b"}, {"type": "dom_style", "selector": "th", "value": "white", "extra": "color", "message": "Header text is white"}, {"type": "dom_style", "selector": "td", "value": "10px", "extra": "padding-top", "message": "Data cells have 10px padding"}]', 1);

INSERT IGNORE INTO lp_tasks (id, module_id, sort_order, title, summary, icon, language, points, is_project, instructions, starter_code, hint, checks, is_published) VALUES
  (301, 3, 1, 'Hello, ICT', 'Your first print()', '👋', 'python', 10, 0, '`print()` shows text on the screen.

**Your job:** print exactly this line:

```
Hello, ICT!
```', '# print the message below
', 'Text goes inside quotes: `print("Hello, ICT!")`', '[{"type": "code_contains", "selector": "", "value": "print", "extra": "", "message": "You use print()"}, {"type": "output_equals", "selector": "", "value": "Hello, ICT!", "extra": "", "message": "The program prints Hello, ICT!"}]', 1);

INSERT IGNORE INTO lp_tasks (id, module_id, sort_order, title, summary, icon, language, points, is_project, instructions, starter_code, hint, checks, is_published) VALUES
  (302, 3, 2, 'Add two numbers', 'input(), int() and arithmetic', '➕', 'python', 10, 0, '`input()` reads text typed by the user, and `int()` turns text into a whole number.

**Your job:** read two whole numbers (one per line) and print their sum. Print only the number.

Use the **Program input** box under the console to try it: type `4` on the first line and `7` on the second.', 'a = int(input())
b = int(input())
# print the sum
', 'Add the numbers with `+` and print the result: `print(a + b)`', '[{"type": "output_equals", "selector": "", "value": "11", "extra": "4\\n7", "message": "Correct sum for the first test"}, {"type": "output_equals", "selector": "", "value": "7", "extra": "10\\n-3", "message": "Correct sum for the second test"}, {"type": "output_equals", "selector": "", "value": "0", "extra": "0\\n0", "message": "Correct sum for the third test"}]', 1);

INSERT IGNORE INTO lp_tasks (id, module_id, sort_order, title, summary, icon, language, points, is_project, instructions, starter_code, hint, checks, is_published) VALUES
  (303, 3, 3, 'Decide the grade', 'if / elif / else', '🎓', 'python', 15, 0, 'Programs make decisions with `if`, `elif` and `else`.

**Your job:** read a mark (a whole number) and print its A/L grade:

- 75 or more: `A`
- 65 to 74: `B`
- 50 to 64: `C`
- 35 to 49: `S`
- below 35: `F`', 'marks = int(input())
# print the grade
', 'Check the highest boundary first: `if marks >= 75:` then `elif marks >= 65:` and so on.', '[{"type": "code_contains", "selector": "", "value": "elif", "extra": "", "message": "You use elif"}, {"type": "output_equals", "selector": "", "value": "A", "extra": "80", "message": "80 gives A"}, {"type": "output_equals", "selector": "", "value": "B", "extra": "70", "message": "70 gives B"}, {"type": "output_equals", "selector": "", "value": "C", "extra": "55", "message": "55 gives C"}, {"type": "output_equals", "selector": "", "value": "S", "extra": "40", "message": "40 gives S"}, {"type": "output_equals", "selector": "", "value": "F", "extra": "20", "message": "20 gives F"}]', 1);

INSERT IGNORE INTO lp_tasks (id, module_id, sort_order, title, summary, icon, language, points, is_project, instructions, starter_code, hint, checks, is_published) VALUES
  (304, 3, 4, 'Loop it', 'for loops and running totals', '🔁', 'python', 15, 0, 'A `for` loop repeats code. `range(1, n + 1)` gives the numbers 1 to n.

**Your job:** read a number `n` and print the sum of all whole numbers from 1 to `n`.

Example: for `5` the answer is `15` because 1 + 2 + 3 + 4 + 5 = 15.', 'n = int(input())
total = 0
# add the numbers 1..n to total using a loop
# print the total
', 'Inside the loop write `total = total + i` (or `total += i`).', '[{"type": "code_regex", "selector": "", "value": "\\\\b(for|while)\\\\b", "extra": "", "message": "You use a loop (for or while)"}, {"type": "output_equals", "selector": "", "value": "15", "extra": "5", "message": "n = 5 gives 15"}, {"type": "output_equals", "selector": "", "value": "55", "extra": "10", "message": "n = 10 gives 55"}, {"type": "output_equals", "selector": "", "value": "1", "extra": "1", "message": "n = 1 gives 1"}]', 1);

INSERT IGNORE INTO lp_tasks (id, module_id, sort_order, title, summary, icon, language, points, is_project, instructions, starter_code, hint, checks, is_published) VALUES
  (305, 3, 5, 'Lists', 'Store many values, find the average', '📚', 'python', 15, 0, 'A list keeps many values in one variable, and Python has helpers such as `sum()` and `len()`.

**Your job:** find the average of the marks in the list and print it.

The average is the sum of the marks divided by how many marks there are.', 'marks = [72, 65, 88, 91, 54]
# print the average of the marks
', '`sum(marks) / len(marks)` gives the average as a decimal.', '[{"type": "code_regex", "selector": "", "value": "len\\\\s*\\\\(", "extra": "", "message": "You count the marks with len()"}, {"type": "output_equals", "selector": "", "value": "74.0", "extra": "", "message": "The program prints the correct average"}]', 1);

INSERT IGNORE INTO lp_tasks (id, module_id, sort_order, title, summary, icon, language, points, is_project, instructions, starter_code, hint, checks, is_published) VALUES
  (306, 3, 6, 'Dictionaries', 'Keys and values', '🗃️', 'python', 15, 0, 'A dictionary stores pairs: a key and its value.

```
student = {"name": "Nimal", "marks": 85}
print(student["name"])
```

**Your job:** use the dictionary below to print exactly `Nimal scored 85`. Read the values from the dictionary; do not type them again.', 'student = {"name": "Nimal", "marks": 85}
# print: Nimal scored 85
', 'Try an f-string: `print(f"{student[''name'']} scored {student[''marks'']}")`', '[{"type": "code_regex", "selector": "", "value": "student\\\\s*\\\\[|student\\\\.get\\\\s*\\\\(", "extra": "", "message": "You read values from the dictionary"}, {"type": "output_equals", "selector": "", "value": "Nimal scored 85", "extra": "", "message": "The program prints Nimal scored 85"}]', 1);

INSERT IGNORE INTO lp_tasks (id, module_id, sort_order, title, summary, icon, language, points, is_project, instructions, starter_code, hint, checks, is_published) VALUES
  (307, 3, 7, 'Handle errors', 'try / except', '🛡️', 'python', 20, 0, 'Users type unexpected things. `try` and `except` stop the program from crashing.

**Your job:** read a value. If it is a whole number, print the number multiplied by 2. Otherwise print `Invalid number`.', 'text = input()
# try to convert text to an int and print it doubled
# if that fails, print: Invalid number
', 'Catch `ValueError`: `try:` convert with `int(text)`, `except ValueError:` print the message.', '[{"type": "code_contains", "selector": "", "value": "try", "extra": "", "message": "You use try"}, {"type": "code_contains", "selector": "", "value": "except", "extra": "", "message": "You use except"}, {"type": "output_equals", "selector": "", "value": "42", "extra": "21", "message": "21 gives 42"}, {"type": "output_equals", "selector": "", "value": "Invalid number", "extra": "abc", "message": "abc gives Invalid number"}, {"type": "output_equals", "selector": "", "value": "-8", "extra": "-4", "message": "-4 gives -8"}]', 1);

INSERT IGNORE INTO lp_tasks (id, module_id, sort_order, title, summary, icon, language, points, is_project, instructions, starter_code, hint, checks, is_published) VALUES
  (401, 4, 1, 'Say hello with PHP', 'PHP tags and echo', '💬', 'php', 10, 0, 'PHP code lives between `<?php` and `?>`. Use `echo` to send text to the browser.

**Your job:** make the script print `Welcome to A/L ICT`.

PHP tasks are checked by reading your code for the required parts, because PHP runs on a server rather than in the browser.', '<?php
// Print: Welcome to A/L ICT
?>
', '`echo "Welcome to A/L ICT";` and do not forget the semicolon.', '[{"type": "code_regex", "selector": "", "value": "<\\\\?php", "extra": "", "message": "The code starts with <?php"}, {"type": "code_regex", "selector": "", "value": "(echo|print)\\\\s*\\\\(?\\\\s*[\\\\\\"'']Welcome to A/L ICT[\\\\\\"'']\\\\s*\\\\)?\\\\s*;", "extra": "", "message": "It echoes exactly: Welcome to A/L ICT"}]', 1);

INSERT IGNORE INTO lp_tasks (id, module_id, sort_order, title, summary, icon, language, points, is_project, instructions, starter_code, hint, checks, is_published) VALUES
  (402, 4, 2, 'Variables', 'Store and print a value', '🧮', 'php', 10, 0, 'PHP variables start with a dollar sign, for example `$name = "Kasun";`.

**Your job:** create a variable `$name` that holds your name and print a greeting that starts with `Hello`.', '<?php
// 1. create $name
// 2. echo a greeting that uses $name
?>
', '`echo "Hello, " . $name;` joins text with a dot.', '[{"type": "code_regex", "selector": "", "value": "\\\\$name\\\\s*=\\\\s*[\\\\\\"''][^\\\\\\"'']+[\\\\\\"'']\\\\s*;", "extra": "", "message": "You create $name with a text value"}, {"type": "code_regex", "selector": "", "value": "echo[^;]*Hello", "extra": "", "message": "You echo a greeting that contains Hello"}, {"type": "code_regex", "selector": "", "value": "echo[^;]*\\\\$name", "extra": "", "message": "The greeting uses the $name variable"}]', 1);

INSERT IGNORE INTO lp_tasks (id, module_id, sort_order, title, summary, icon, language, points, is_project, instructions, starter_code, hint, checks, is_published) VALUES
  (403, 4, 3, 'Handle a form', '$_POST and safe output', '📮', 'php', 20, 0, 'Forms send data to PHP. With `method="post"` the values arrive in the `$_POST` array.

**Your job:** when the form below is submitted, print `Hello, ` and the submitted name.

Always escape user input with `htmlspecialchars()` before printing it, so nobody can inject HTML into your page.', '<form method="post">
  <input type="text" name="name" placeholder="Your name">
  <button type="submit">Send</button>
</form>

<?php
// if the form was submitted, print: Hello, <name>
?>
', 'Wrap the code in `if ($_SERVER["REQUEST_METHOD"] === "POST") { ... }` and read `$_POST["name"]`.', '[{"type": "code_regex", "selector": "", "value": "\\\\$_SERVER\\\\s*\\\\[\\\\s*[\\\\\\"'']REQUEST_METHOD[\\\\\\"'']\\\\s*\\\\]\\\\s*===?\\\\s*[\\\\\\"'']POST[\\\\\\"'']|isset\\\\s*\\\\(\\\\s*\\\\$_POST", "extra": "", "message": "You check that the form was submitted"}, {"type": "code_regex", "selector": "", "value": "\\\\$_POST\\\\s*\\\\[\\\\s*[\\\\\\"'']name[\\\\\\"'']\\\\s*\\\\]", "extra": "", "message": "You read $_POST[\\"name\\"]"}, {"type": "code_regex", "selector": "", "value": "htmlspecialchars\\\\s*\\\\(", "extra": "", "message": "You escape the input with htmlspecialchars()"}, {"type": "code_regex", "selector": "", "value": "echo|print", "extra": "", "message": "You print the greeting"}]', 1);

INSERT IGNORE INTO lp_tasks (id, module_id, sort_order, title, summary, icon, language, points, is_project, instructions, starter_code, hint, checks, is_published) VALUES
  (404, 4, 4, 'Connect to MySQL', 'mysqli and a SELECT query', '🔌', 'php', 20, 0, 'Dynamic pages read data from MySQL. The steps are always the same: connect, check the connection, run a query.

**Your job:** connect to the database `school` (user `root`, empty password on localhost), stop with a message if the connection fails, then run this query:

```
SELECT name, marks FROM students
```', '<?php
// 1. connect to the database "school"
// 2. stop with an error message if the connection fails
// 3. run: SELECT name, marks FROM students
?>
', '`$conn = mysqli_connect("localhost", "root", "", "school");` then `if (!$conn) { die(...); }` then `mysqli_query($conn, $sql);`', '[{"type": "code_regex", "selector": "", "value": "mysqli_connect\\\\s*\\\\(|new\\\\s+mysqli\\\\s*\\\\(|new\\\\s+PDO\\\\s*\\\\(", "extra": "", "message": "You open a connection (mysqli or PDO)"}, {"type": "code_regex", "selector": "", "value": "mysqli_connect_error|connect_error|PDOException|getMessage", "extra": "", "message": "You report connection errors"}, {"type": "code_regex", "selector": "", "value": "die\\\\s*\\\\(|exit\\\\s*[\\\\(;]", "extra": "", "message": "You stop the script when it fails"}, {"type": "code_regex", "selector": "", "value": "SELECT\\\\s+name\\\\s*,\\\\s*marks\\\\s+FROM\\\\s+students", "extra": "", "message": "The SELECT query is written correctly"}, {"type": "code_regex", "selector": "", "value": "mysqli_query\\\\s*\\\\(|->query\\\\s*\\\\(|->prepare\\\\s*\\\\(", "extra": "", "message": "You run the query"}]', 1);

INSERT IGNORE INTO lp_tasks (id, module_id, sort_order, title, summary, icon, language, points, is_project, instructions, starter_code, hint, checks, is_published) VALUES
  (501, 5, 1, 'Project: Personal profile page', 'HTML + CSS, one full page', '👤', 'html', 30, 1, 'Build a one-page profile that would suit a school portfolio.

Your page needs:

- a `<header>` with your name in an `<h1>`
- a `<nav>` with at least 3 links (`<a>`)
- a `<main>` with an `<h2>`, a `<ul>` of 3 skills and a `<table>` of subjects and grades (one header row plus 3 rows)
- a `<footer>` with a short line of text

Then style it with CSS:

- `body` background colour `#f1f5f9`
- `footer` text centred
- links inside `nav` in the colour `navy`', '<!DOCTYPE html>
<html>
<head>
  <title>My profile</title>
  <style>
    /* your CSS here */
  </style>
</head>
<body>
  <!-- header, nav, main and footer go here -->
</body>
</html>
', 'Use `href="#"` for links you have no page for yet. In CSS the selector for the links is `nav a`.', '[{"type": "dom_text_contains", "selector": "header h1", "value": "", "extra": "", "message": "<header> contains an <h1> with your name"}, {"type": "dom_count_min", "selector": "nav a", "value": "3", "extra": "", "message": "<nav> has at least 3 links"}, {"type": "dom_exists", "selector": "main h2", "value": "", "extra": "", "message": "<main> has an <h2>"}, {"type": "dom_count_min", "selector": "main ul li", "value": "3", "extra": "", "message": "<main> has a list of 3 skills"}, {"type": "dom_count_min", "selector": "main table tr", "value": "4", "extra": "", "message": "The table has a header row and 3 rows"}, {"type": "dom_text_contains", "selector": "footer", "value": "", "extra": "", "message": "<footer> has some text"}, {"type": "dom_style", "selector": "body", "value": "#f1f5f9", "extra": "background-color", "message": "Body background is #f1f5f9"}, {"type": "dom_style", "selector": "footer", "value": "center", "extra": "text-align", "message": "Footer text is centred"}, {"type": "dom_style", "selector": "nav a", "value": "navy", "extra": "color", "message": "Navigation links are navy"}]', 1);

INSERT IGNORE INTO lp_tasks (id, module_id, sort_order, title, summary, icon, language, points, is_project, instructions, starter_code, hint, checks, is_published) VALUES
  (502, 5, 2, 'Project: Marks analyser', 'Loops, lists and formatted output', '📈', 'python', 30, 1, 'Write a program for a class teacher.

Read the number of students `n`, then read `n` marks (one per line, whole numbers).

Print three lines in this format (this example is for the marks 60, 70 and 80):

```
Total: 210
Average: 70.0
Highest: 80
```

Use a loop and a list. The average must be a decimal, so use `/` (not `//`).', 'n = int(input())
marks = []
# read n marks into the list

# print Total, Average and Highest
', 'Loop `n` times with `for _ in range(n):` and `marks.append(int(input()))`. Then use `sum(marks)`, `len(marks)` and `max(marks)`.', '[{"type": "code_regex", "selector": "", "value": "\\\\b(for|while)\\\\b", "extra": "", "message": "You use a loop"}, {"type": "output_contains", "selector": "", "value": "Total: 210", "extra": "3\\n60\\n70\\n80", "message": "Total is correct"}, {"type": "output_contains", "selector": "", "value": "Average: 70.0", "extra": "3\\n60\\n70\\n80", "message": "Average is correct"}, {"type": "output_contains", "selector": "", "value": "Highest: 80", "extra": "3\\n60\\n70\\n80", "message": "Highest is correct"}, {"type": "output_equals", "selector": "", "value": "Total: 240\\nAverage: 60.0\\nHighest: 90", "extra": "4\\n50\\n90\\n70\\n30", "message": "A second test with 4 students also passes"}]', 1);

INSERT IGNORE INTO lp_tasks (id, module_id, sort_order, title, summary, icon, language, points, is_project, instructions, starter_code, hint, checks, is_published) VALUES
  (503, 5, 3, 'Project: Contact book', 'Dictionaries and lookups', '📒', 'python', 30, 1, 'Build a tiny phone book.

The dictionary below holds names and phone numbers. Read a name and print that person''s number. If the name is not in the book, print `Not found`.', 'contacts = {"Kamal": "0771234567", "Nimali": "0712345678", "Ruwan": "0751122334"}

name = input()
# look the name up and print the number, or Not found
', '`if name in contacts:` print `contacts[name]`, `else:` print the message.', '[{"type": "code_regex", "selector": "", "value": "\\\\bin\\\\s+contacts|contacts\\\\.get\\\\s*\\\\(|contacts\\\\s*\\\\[", "extra": "", "message": "You look the name up in the dictionary"}, {"type": "output_equals", "selector": "", "value": "0771234567", "extra": "Kamal", "message": "Kamal is found"}, {"type": "output_equals", "selector": "", "value": "0751122334", "extra": "Ruwan", "message": "Ruwan is found"}, {"type": "output_equals", "selector": "", "value": "Not found", "extra": "Sunil", "message": "An unknown name prints Not found"}]', 1);

INSERT IGNORE INTO lp_tasks (id, module_id, sort_order, title, summary, icon, language, points, is_project, instructions, starter_code, hint, checks, is_published) VALUES
  (504, 5, 4, 'Project: Registration handler', 'PHP validation + prepared INSERT', '📝', 'php', 30, 1, 'Write the PHP that handles a registration form.

When the form is submitted your script must:

- read `name` and `email` from `$_POST`
- make sure neither is empty
- validate the email with `filter_var()` and `FILTER_VALIDATE_EMAIL`
- save the person with a **prepared** `INSERT INTO` statement

Prepared statements protect your database from SQL injection, which is a favourite exam topic.', '<?php
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // 1. read name and email from $_POST
    // 2. make sure neither is empty
    // 3. validate the email
    // 4. save with a prepared INSERT statement
}
?>
<form method="post">
  <input name="name" placeholder="Name">
  <input name="email" placeholder="Email">
  <button>Register</button>
</form>
', '`$stmt = $pdo->prepare("INSERT INTO students (name, email) VALUES (?, ?)");` then `$stmt->execute([$name, $email]);`', '[{"type": "code_regex", "selector": "", "value": "\\\\$_POST\\\\s*\\\\[\\\\s*[\\\\\\"'']name[\\\\\\"'']\\\\s*\\\\]", "extra": "", "message": "You read the name from $_POST"}, {"type": "code_regex", "selector": "", "value": "\\\\$_POST\\\\s*\\\\[\\\\s*[\\\\\\"'']email[\\\\\\"'']\\\\s*\\\\]", "extra": "", "message": "You read the email from $_POST"}, {"type": "code_regex", "selector": "", "value": "empty\\\\s*\\\\(|trim\\\\s*\\\\(", "extra": "", "message": "You check for empty values"}, {"type": "code_regex", "selector": "", "value": "filter_var\\\\s*\\\\([^;]*FILTER_VALIDATE_EMAIL", "extra": "", "message": "You validate the email with filter_var()"}, {"type": "code_regex", "selector": "", "value": "INSERT\\\\s+INTO", "extra": "", "message": "You write an INSERT INTO query"}, {"type": "code_regex", "selector": "", "value": "prepare\\\\s*\\\\(", "extra": "", "message": "The query is prepared"}, {"type": "code_regex", "selector": "", "value": "execute\\\\s*\\\\(|bind_param\\\\s*\\\\(", "extra": "", "message": "You run it with execute() or bind_param()"}]', 1);