<?php
// php tests/php-performance.php
// TEST_DB_* can override the local TypeScript database used as a schema source.
require_once __DIR__ . '/../includes/quiz-data.php';
require_once __DIR__ . '/../includes/pagination.php';
$pdo = new PDO(
    'mysql:host=' . (getenv('TEST_DB_HOST') ?: '127.0.0.1') . ';port=' . (getenv('TEST_DB_PORT') ?: '3308') .
    ';dbname=' . (getenv('TEST_DB_NAME') ?: 'elearning_tsx') . ';charset=utf8mb4',
    getenv('TEST_DB_USER') ?: 'root', getenv('TEST_DB_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
$source = $pdo->query('SELECT DATABASE()')->fetchColumn();
$fixture = 'elearning_php_perf_test_' . bin2hex(random_bytes(6));
if (!preg_match('/^[a-zA-Z0-9_]+$/D', $source)) throw new RuntimeException('Invalid schema name');
$checks = 0;
function check_result(bool $condition, string $label): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($label);
    $checks++;
}
try {
    $pdo->exec("CREATE DATABASE `$fixture` CHARACTER SET utf8mb4");
    foreach (['users', 'questions', 'question_options', 'quiz_attempts', 'student_answers'] as $table) {
        $pdo->exec("CREATE TABLE `$fixture`.`$table` LIKE `$source`.`$table`");
    }
    $pdo->exec("USE `$fixture`");
    check_result($pdo->query('SELECT DATABASE()')->fetchColumn() === $fixture, 'Fixture isolation');
    $pdo->exec("INSERT INTO users(user_id,username,password_hash,first_name,last_name,email,role) VALUES
        (1,'one','test','First','Student','one@test.invalid','student'),
        (2,'two','test','Second','Student','two@test.invalid','student')");
    $pdo->exec("INSERT INTO questions(question_id,quiz_id,question_text,points) VALUES
        (1,1,'One',3),(2,1,'Two',1),(3,1,'Three',1),(4,1,'Four',1),(5,2,'Other quiz',1)");
    $pdo->exec("INSERT INTO question_options(option_id,question_id,option_text,is_correct) VALUES
        (1,1,'Correct',1),(2,1,'Wrong',0),(3,2,'Correct',1),(4,2,'Wrong',0),
        (5,3,'Correct',1),(6,3,'Wrong',0),(7,4,'Correct',1),(8,4,'Wrong',0),(9,5,'Other',1)");
    $public = elearning_quiz_options($pdo, 1);
    check_result(count($public) === 4 && count($public[1]) === 2, 'Grouped options');
    check_result(!array_key_exists('is_correct', $public[1][0]), 'Student response excludes answers');
    check_result(!isset($public[5]), 'Other quiz is excluded');
    $questions = $pdo->query('SELECT question_id,points FROM questions WHERE quiz_id=1 ORDER BY question_id')->fetchAll();
    $private = elearning_quiz_options($pdo, 1, true);
    $graded = elearning_grade_quiz($questions, $private, ['q_1'=>1, 'q_2'=>3, 'q_3'=>6]);
    check_result($graded['score_percent'] === 66.67 && $graded['correct_count'] === 2, 'Weighted score preserves PHP precision');
    check_result(count($graded['answers']) === 4 && $graded['answers'][3]['option_id'] === null, 'Unanswered questions stay null');
    $rejected = false;
    try { elearning_grade_quiz($questions, $private, ['q_1'=>3]); } catch (InvalidArgumentException $e) { $rejected = true; }
    check_result($rejected, 'Options from another question are rejected');
    check_result(elearning_grade_quiz($questions, $private, [])['score_percent'] == 0, 'Empty submission scores zero');
    $pdo->beginTransaction();
    elearning_save_answers($pdo, 1, $graded['answers']);
    check_result((int)$pdo->query('SELECT COUNT(*) FROM student_answers')->fetchColumn() === 3, 'Batch saves omit unanswered options required by the schema');
    $pdo->rollBack();
    check_result((int)$pdo->query('SELECT COUNT(*) FROM student_answers')->fetchColumn() === 0, 'Batch writes roll back');
    elearning_save_answers($pdo, 2, array_fill(0, 501, ['question_id'=>1, 'option_id'=>1, 'is_correct'=>1]));
    check_result((int)$pdo->query('SELECT COUNT(*) FROM student_answers')->fetchColumn() === 501, '501 answers cross the batch boundary');
    $pdo->exec("INSERT INTO quiz_attempts(user_id,quiz_id,score_obtained,is_passed,time_spent_seconds,attempt_date) VALUES
        (1,1,100,1,120,'2026-01-01'),(1,1,10,0,20,'2026-01-02'),(2,1,100,1,90,'2026-01-01')");
    $stmt = $pdo->prepare(elearning_leaderboard_sql());
    $stmt->execute([1]);
    $ranking = $stmt->fetchAll();
    check_result(count($ranking) === 2 && (int)$ranking[0]['user_id'] === 2 && (int)$ranking[1]['best_time'] === 120, 'Best score uses time from the same attempt');
    $_GET['page'] = 999;
    $paging = elearning_pagination(41);
    check_result($paging['page'] === 3 && $paging['offset'] === 40, 'Pagination clamps the last page');
    check_result(elearning_pagination(0)['page'] === 1, 'Empty pagination stays valid');
    ob_start(); elearning_render_pagination(elearning_pagination(2000000)); $html = ob_get_clean();
    check_result(substr_count($html, '<a ') <= 2 && strlen($html) < 1000, 'Pagination controls remain bounded');
    echo "PHP regression checks passed: $checks\n";
} finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if (preg_match('/^elearning_php_perf_test_[0-9a-f]+$/D', $fixture)) {
        $pdo->exec("USE `$source`");
        $pdo->exec("DROP DATABASE IF EXISTS `$fixture`");
    }
}
