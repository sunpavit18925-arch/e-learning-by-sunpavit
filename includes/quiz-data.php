<?php
// Fetch a quiz's options once. Answers are only selected for server-side grading.
function elearning_quiz_options(PDO $pdo, int $quiz_id, bool $include_answers = false): array
{
    $sql = 'SELECT o.option_id, o.question_id, o.option_text' . ($include_answers ? ', o.is_correct' : '') . '
            FROM question_options o JOIN questions q ON q.question_id = o.question_id
            WHERE q.quiz_id = ? ORDER BY o.question_id, o.option_id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$quiz_id]);
    $grouped = [];
    while ($option = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $grouped[(int)$option['question_id']][] = $option;
    }
    return $grouped;
}

function elearning_grade_quiz(array $questions, array $options_by_question, array $submitted): array
{
    $earned = 0;
    $total = 0;
    $correct = 0;
    $answers = [];
    foreach ($questions as $question) {
        $question_id = (int)$question['question_id'];
        $points = max(1, (int)$question['points']);
        $total += $points;
        $selected = (int)($submitted['q_' . $question_id] ?? 0);
        $is_correct = 0;
        if ($selected > 0) {
            // Each question's options are indexed once, rather than queried individually.
            $options = array_column($options_by_question[$question_id] ?? [], null, 'option_id');
            if (!isset($options[$selected])) {
                throw new InvalidArgumentException('ตัวเลือกไม่ถูกต้อง');
            }
            $is_correct = (int)$options[$selected]['is_correct'] === 1 ? 1 : 0;
            if ($is_correct) {
                $earned += $points;
                $correct++;
            }
        }
        $answers[] = ['question_id' => $question_id, 'option_id' => $selected > 0 ? $selected : null, 'is_correct' => $is_correct];
    }
    return ['score_percent' => $total > 0 ? round($earned / $total * 100, 2) : 0,
            'correct_count' => $correct, 'answers' => $answers];
}

function elearning_save_answers(PDO $pdo, int $attempt_id, array $answers): void
{
    // The schema requires option_id. Unanswered questions count toward the score
    // but have no student_answers row, matching the TypeScript implementation.
    $answers = array_values(array_filter($answers, static function (array $answer): bool {
        return $answer['option_id'] !== null;
    }));
    foreach (array_chunk($answers, 500) as $batch) {
        $params = [];
        foreach ($batch as $answer) {
            array_push($params, $attempt_id, $answer['question_id'], $answer['option_id'], $answer['is_correct']);
        }
        $sql = 'INSERT INTO student_answers (attempt_id, question_id, option_id, is_correct) VALUES ' .
               implode(',', array_fill(0, count($batch), '(?,?,?,?)'));
        $pdo->prepare($sql)->execute($params);
    }
}

function elearning_leaderboard_sql(): string
{
    return 'SELECT a.user_id,u.username,u.first_name,u.last_name,u.image,
                   a.score_obtained AS top_score,a.time_spent_seconds AS best_time,
                   a.has_passed,a.last_attempt_date
            FROM (
                SELECT user_id,score_obtained,time_spent_seconds,
                       MAX(is_passed) OVER (PARTITION BY user_id) AS has_passed,
                       MAX(attempt_date) OVER (PARTITION BY user_id) AS last_attempt_date,
                       ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY score_obtained DESC,
                           time_spent_seconds ASC,attempt_date DESC,attempt_id DESC) AS position
                FROM quiz_attempts WHERE quiz_id = ?
            ) a JOIN users u ON u.user_id = a.user_id
            WHERE a.position = 1
            ORDER BY top_score DESC,best_time ASC,last_attempt_date ASC,a.user_id ASC LIMIT 10';
}
