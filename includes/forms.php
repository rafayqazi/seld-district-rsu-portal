<?php
/**
 * Shared validation and status helpers for district forms.
 */

function lsu_form_questions(array $form): array {
    $questions = json_decode((string)($form['questions_json'] ?? ''), true);
    return is_array($questions) ? $questions : [];
}

function lsu_form_is_open(array $form, ?int $timestamp = null): bool {
    $timestamp = $timestamp ?? time();
    $closesAt = strtotime((string)($form['closes_at'] ?? ''));
    return ($form['status'] ?? '') === 'Published' && $closesAt !== false && $closesAt > $timestamp;
}

function lsu_form_status_label(array $form, ?int $timestamp = null): string {
    if (($form['status'] ?? '') === 'Published' && !lsu_form_is_open($form, $timestamp)) {
        return 'Expired';
    }
    return (string)($form['status'] ?? 'Draft');
}

function lsu_form_validate_questions(string $json): array {
    $decoded = json_decode($json, true);
    if (!is_array($decoded) || count($decoded) < 1 || count($decoded) > 50) {
        return ['valid' => false, 'questions' => [], 'error' => 'Add between 1 and 50 questions before saving the form.'];
    }

    $questions = [];
    $allowedTypes = ['short_text', 'long_text', 'number', 'date', 'radio', 'checkbox', 'dropdown'];
    foreach ($decoded as $question) {
        if (!is_array($question)) {
            return ['valid' => false, 'questions' => [], 'error' => 'One of the questions is invalid.'];
        }

        if (!is_scalar($question['label'] ?? null) || !is_scalar($question['type'] ?? null)) {
            return ['valid' => false, 'questions' => [], 'error' => 'One of the questions is invalid.'];
        }
        $label = trim((string)$question['label']);
        $type = (string)$question['type'];
        if ($label === '' || mb_strlen($label) > 250 || !in_array($type, $allowedTypes, true)) {
            return ['valid' => false, 'questions' => [], 'error' => 'Every question needs a label and a valid question type.'];
        }

        $options = [];
        if (in_array($type, ['radio', 'checkbox', 'dropdown'], true)) {
            $rawOptions = $question['options'] ?? [];
            if (!is_array($rawOptions)) {
                return ['valid' => false, 'questions' => [], 'error' => 'Choice questions need valid options.'];
            }
            foreach ($rawOptions as $option) {
                if (!is_scalar($option)) {
                    return ['valid' => false, 'questions' => [], 'error' => 'Choice question options must be text.'];
                }
                $option = trim((string)$option);
                if (mb_strlen($option) > 250) {
                    return ['valid' => false, 'questions' => [], 'error' => 'Each choice option must be 250 characters or fewer.'];
                }
                if ($option !== '' && !in_array($option, $options, true)) {
                    $options[] = $option;
                }
            }
            if (count($options) < 2 || count($options) > 30) {
                return ['valid' => false, 'questions' => [], 'error' => 'Each choice question needs 2 to 30 unique options.'];
            }
        }

        $questions[] = [
            'id' => bin2hex(random_bytes(8)),
            'label' => $label,
            'type' => $type,
            'required' => !empty($question['required']),
            'options' => $options,
        ];
    }

    return ['valid' => true, 'questions' => $questions, 'error' => ''];
}

function lsu_form_validate_answers(array $questions, array $submitted): array {
    $answers = [];
    foreach ($questions as $question) {
        $id = (string)($question['id'] ?? '');
        $type = (string)($question['type'] ?? '');
        if ($id === '') {
            return ['valid' => false, 'answers' => [], 'error' => 'This form contains an invalid question. Please contact the District RSU.'];
        }

        $value = $submitted[$id] ?? '';
        if ($type === 'checkbox') {
            if (!is_array($value)) {
                if ($value !== '' && $value !== null) {
                    return ['valid' => false, 'answers' => [], 'error' => 'A selected answer is not valid for this form.'];
                }
                $value = [];
            }
            foreach ($value as $answer) {
                if (!is_scalar($answer)) {
                    return ['valid' => false, 'answers' => [], 'error' => 'A selected answer is not valid for this form.'];
                }
            }
        } elseif (!is_scalar($value) && $value !== null) {
            return ['valid' => false, 'answers' => [], 'error' => 'A submitted answer is not valid for this form.'];
        }
        $isEmpty = is_array($value) ? count(array_filter($value, fn($v) => trim((string)$v) !== '')) === 0 : trim((string)$value) === '';
        if (!empty($question['required']) && $isEmpty) {
            return ['valid' => false, 'answers' => [], 'error' => 'Please answer all required questions.'];
        }
        if ($isEmpty) {
            $answers[$id] = '';
            continue;
        }

        if (in_array($type, ['radio', 'dropdown'], true)) {
            $value = is_string($value) ? trim($value) : '';
            if (!in_array($value, $question['options'] ?? [], true)) {
                return ['valid' => false, 'answers' => [], 'error' => 'A selected answer is not valid for this form.'];
            }
        } elseif ($type === 'checkbox') {
            $value = array_values(array_unique(array_filter(array_map('trim', $value), fn($v) => $v !== '')));
            if (array_diff($value, $question['options'] ?? [])) {
                return ['valid' => false, 'answers' => [], 'error' => 'A selected answer is not valid for this form.'];
            }
        } elseif ($type === 'number') {
            $value = trim((string)$value);
            if (!is_numeric($value) || mb_strlen($value) > 40) {
                return ['valid' => false, 'answers' => [], 'error' => 'Enter a valid number for each number question.'];
            }
        } elseif ($type === 'date') {
            $value = trim((string)$value);
            $date = DateTime::createFromFormat('!Y-m-d', $value);
            if (!$date || $date->format('Y-m-d') !== $value) {
                return ['valid' => false, 'answers' => [], 'error' => 'Enter a valid date for each date question.'];
            }
        } else {
            $value = trim((string)$value);
            $maxLength = $type === 'long_text' ? 5000 : 250;
            if (mb_strlen($value) > $maxLength) {
                return ['valid' => false, 'answers' => [], 'error' => 'One or more text answers are too long.'];
            }
        }

        $answers[$id] = $value;
    }

    return ['valid' => true, 'answers' => $answers, 'error' => ''];
}
