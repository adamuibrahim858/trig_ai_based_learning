<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Test;
use App\Models\Topic;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ImportCurriculumFromMarkdown extends Command
{
    protected $signature = 'curriculum:import {--file= : Path to curriculum markdown file}';
    protected $description = 'Import 8-week Secondary School Trigonometry curriculum idempotently into the database';

    public function handle(): int
    {
        $this->info('Starting idempotent curriculum import...');

        $datasetPath = database_path('seeders/curriculum_dataset.json');
        $questionsPath = database_path('seeders/questions_dataset.json');

        if (!File::exists($datasetPath) || !File::exists($questionsPath)) {
            $this->error('Required curriculum datasets not found in database/seeders.');
            return Command::FAILURE;
        }

        $curriculum = json_decode(File::get($datasetPath), true);
        $questionsByWeek = json_decode(File::get($questionsPath), true);

        DB::transaction(function () use ($curriculum, $questionsByWeek) {
            $course = Course::updateOrCreate(
                ['slug' => 'basic-trigonometry'],
                [
                    'title' => 'Secondary School Trigonometry',
                    'description' => 'A comprehensive 8-week structured foundational mastery course in Trigonometry, taking students from basic angle properties and right-angled triangles to trigonometric ratios, inverse functions, real-world elevations, and comprehensive problem solving.',
                    'learning_objectives' => [
                        'Understand geometric foundations of angles and triangle properties.',
                        'Identify sides of right-angled triangles relative to acute reference angles.',
                        'Apply Pythagoras theorem to calculate hypotenuse and leg lengths.',
                        'Formulate and evaluate Sine, Cosine, and Tangent ratios (SOH-CAH-TOA).',
                        'Calculate unknown side lengths in right triangles.',
                        'Use inverse trigonometric functions to determine unknown angle measures.',
                        'Solve real-world problems involving angles of elevation and depression.',
                        'Synthesize multi-step trigonometric strategies for mastery.',
                    ],
                    'status' => 'published',
                ]
            );

            $this->line("Course: {$course->title} (ID: {$course->id})");

            $allQuestionsForFinalExam = [];
            $totalLessonsImported = 0;
            $totalQuestionsImported = 0;

            foreach ($curriculum as $topicData) {
                $weekNumber = $topicData['week_number'];

                $topic = Topic::updateOrCreate(
                    [
                        'course_id' => $course->id,
                        'week_number' => $weekNumber,
                    ],
                    [
                        'title' => $topicData['title'],
                        'slug' => $topicData['slug'],
                        'description' => $topicData['description'],
                        'learning_objectives' => $topicData['learning_objectives'],
                        'order' => $weekNumber,
                    ]
                );

                $this->line(" → Week {$weekNumber}: {$topic->title}");

                foreach ($topicData['lessons'] as $lessonData) {
                    Lesson::updateOrCreate(
                        [
                            'topic_id' => $topic->id,
                            'slug' => $lessonData['slug'],
                        ],
                        [
                            'title' => $lessonData['title'],
                            'summary' => $lessonData['summary'],
                            'content' => $lessonData['content'],
                            'learning_objectives' => $lessonData['learning_objectives'] ?? null,
                            'estimated_minutes' => $lessonData['estimated_minutes'] ?? 15,
                            'order' => $lessonData['order'],
                            'is_published' => true,
                        ]
                    );
                    $totalLessonsImported++;
                }

                // Topic Assessment
                $weekQuestions = $questionsByWeek[$weekNumber] ?? [];
                if (!empty($weekQuestions)) {
                    $test = Test::updateOrCreate(
                        [
                            'topic_id' => $topic->id,
                            'type' => 'topic_assessment',
                        ],
                        [
                            'course_id' => $course->id,
                            'lesson_id' => null,
                            'title' => "Week {$weekNumber} Mastery Assessment",
                            'description' => "Assess understanding and mastery of concepts taught in Week {$weekNumber}.",
                            'pass_mark' => 70.00,
                            'total_marks' => count($weekQuestions),
                            'duration_minutes' => 30,
                            'attempts_allowed' => null,
                            'is_published' => true,
                        ]
                    );

                    foreach ($weekQuestions as $qIndex => $qData) {
                        $question = Question::updateOrCreate(
                            [
                                'test_id' => $test->id,
                                'order' => $qIndex + 1,
                            ],
                            [
                                'question_text' => $qData['question_text'],
                                'question_type' => $qData['question_type'],
                                'explanation' => $qData['explanation'],
                                'marks' => $qData['marks'] ?? 1.00,
                                'metadata' => $qData['metadata'] ?? null,
                            ]
                        );
                        $totalQuestionsImported++;

                        if ($qIndex < 2) {
                            $allQuestionsForFinalExam[] = $qData;
                        }

                        if (!empty($qData['options'])) {
                            foreach ($qData['options'] as $oIndex => $optData) {
                                QuestionOption::updateOrCreate(
                                    [
                                        'question_id' => $question->id,
                                        'order' => $oIndex + 1,
                                    ],
                                    [
                                        'option_text' => $optData['text'],
                                        'is_correct' => $optData['is_correct'],
                                    ]
                                );
                            }
                        }
                    }
                }
            }

            // Final Comprehensive Examination
            $finalExam = Test::updateOrCreate(
                [
                    'course_id' => $course->id,
                    'type' => 'final_exam',
                ],
                [
                    'topic_id' => null,
                    'lesson_id' => null,
                    'title' => 'Trigonometry Comprehensive Final Examination',
                    'description' => 'Official final examination assessing complete mastery of all eight weeks of Secondary School Trigonometry. Requires 70% or higher to earn the course completion certificate.',
                    'pass_mark' => 70.00,
                    'total_marks' => count($allQuestionsForFinalExam),
                    'duration_minutes' => 60,
                    'attempts_allowed' => 5,
                    'is_published' => true,
                ]
            );

            foreach ($allQuestionsForFinalExam as $fIndex => $qData) {
                $fQuestion = Question::updateOrCreate(
                    [
                        'test_id' => $finalExam->id,
                        'order' => $fIndex + 1,
                    ],
                    [
                        'question_text' => $qData['question_text'],
                        'question_type' => $qData['question_type'],
                        'explanation' => $qData['explanation'],
                        'marks' => $qData['marks'] ?? 1.00,
                        'metadata' => $qData['metadata'] ?? null,
                    ]
                );

                if (!empty($qData['options'])) {
                    foreach ($qData['options'] as $oIndex => $optData) {
                        QuestionOption::updateOrCreate(
                            [
                                'question_id' => $fQuestion->id,
                                'order' => $oIndex + 1,
                            ],
                            [
                                'option_text' => $optData['text'],
                                'is_correct' => $optData['is_correct'],
                            ]
                        );
                    }
                }
            }

            $this->info("Import completed successfully!");
            $this->info("Weeks: 8 | Lessons: {$totalLessonsImported} | Questions: {$totalQuestionsImported}");
        });

        return Command::SUCCESS;
    }
}
