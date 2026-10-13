<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonResource;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Test;
use App\Models\Topic;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CourseCurriculumSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $this->seedCourse();
        });
    }

    private function seedCourse(): void
    {
        $course = Course::updateOrCreate(
            ['slug' => 'basic-trigonometry'],
            [
                'title' => 'Basic Trigonometry',
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

        $curriculum = $this->getCurriculumData();

        $allQuestionsForFinalExam = [];

        foreach ($curriculum as $topicData) {
            $topic = Topic::updateOrCreate(
                [
                    'course_id' => $course->id,
                    'slug' => $topicData['slug'],
                ],
                [
                    'title' => $topicData['title'],
                    'description' => $topicData['description'],
                    'week_number' => $topicData['week_number'],
                    'learning_objectives' => $topicData['learning_objectives'],
                    'order' => $topicData['week_number'],
                ]
            );

            foreach ($topicData['lessons'] as $lIndex => $lessonData) {
                $lesson = Lesson::updateOrCreate(
                    [
                        'topic_id' => $topic->id,
                        'slug' => $lessonData['slug'],
                    ],
                    [
                        'title' => $lessonData['title'],
                        'summary' => $lessonData['summary'],
                        'content' => $lessonData['content'],
                        'learning_objectives' => $lessonData['learning_objectives'],
                        'estimated_minutes' => $lessonData['estimated_minutes'] ?? 20,
                        'order' => $lIndex + 1,
                        'is_published' => true,
                    ]
                );

                if (!empty($lessonData['resources'])) {
                    foreach ($lessonData['resources'] as $rIndex => $res) {
                        LessonResource::updateOrCreate(
                            [
                                'lesson_id' => $lesson->id,
                                'title' => $res['title'],
                            ],
                            [
                                'type' => $res['type'],
                                'description' => $res['description'] ?? null,
                                'content_url' => $res['content_url'],
                                'order' => $rIndex + 1,
                            ]
                        );
                    }
                }
            }

            // Topic Assessment Test
            if (!empty($topicData['assessment'])) {
                $assessment = $topicData['assessment'];
                $test = Test::updateOrCreate(
                    [
                        'topic_id' => $topic->id,
                        'type' => 'topic_assessment',
                    ],
                    [
                        'course_id' => $course->id,
                        'lesson_id' => null,
                        'title' => $assessment['title'],
                        'description' => $assessment['description'],
                        'pass_mark' => 70.00,
                        'total_marks' => count($assessment['questions']),
                        'duration_minutes' => $assessment['duration_minutes'] ?? 30,
                        'attempts_allowed' => null, // unlimited retakes allowed until passed
                        'is_published' => true,
                    ]
                );

                foreach ($assessment['questions'] as $qIndex => $qData) {
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

                    // Keep sample for final exam pool
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

        // Final Examination Test
        $finalExam = Test::updateOrCreate(
            [
                'course_id' => $course->id,
                'type' => 'final_exam',
            ],
            [
                'topic_id' => null,
                'lesson_id' => null,
                'title' => 'Trigonometry Comprehensive Final Examination',
                'description' => 'Official final examination assessing complete mastery of all eight weeks of Basic Trigonometry. Requires 70% or higher to earn the course completion certificate.',
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
                foreach ($qData['options'] as $foIndex => $optData) {
                    QuestionOption::updateOrCreate(
                        [
                            'question_id' => $fQuestion->id,
                            'order' => $foIndex + 1,
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

    private function getCurriculumData(): array
    {
        return [
            // ==========================================
            // WEEK 1: FOUNDATION
            // ==========================================
            [
                'week_number' => 1,
                'title' => 'Foundation: Angles and Triangle Properties',
                'slug' => 'week-1-foundation',
                'description' => 'Explore the origins of trigonometry, angle measurement, triangle classifications, and fundamental geometric angle relationships.',
                'learning_objectives' => [
                    'Define the meaning and origin of trigonometry.',
                    'Classify angles by measure (acute, right, obtuse, straight, reflex).',
                    'Differentiate triangle types by sides and angles.',
                    'Apply the Triangle Angle Sum property (180 degrees).',
                ],
                'lessons' => [
                    [
                        'title' => 'Meaning of Trigonometry and Understanding Angles',
                        'slug' => 'meaning-of-trigonometry-and-understanding-angles',
                        'summary' => 'Introduction to trigonometric etymology, real-world utility, and angle classification fundamentals.',
                        'learning_objectives' => [
                            'Identify trigonometry as the study of triangle measurements.',
                            'Distinguish between acute, right, obtuse, straight, and reflex angles.',
                        ],
                        'estimated_minutes' => 20,
                        'content' => <<<'MARKDOWN'
# Meaning of Trigonometry and Angles

## 1. Etymology and Purpose
The word **Trigonometry** is derived from Greek words:
- **Trigonon** (τρῐ́γωνον) meaning *triangle*.
- **Metron** (μέτρον) meaning *measurement*.

Fundamentally, trigonometry is the branch of mathematics that explores relationships between the side lengths and angles of triangles. While invented millennia ago for astronomy and land surveying, it forms the backbone of modern satellite navigation (GPS), robotics, game physics, architecture, and computer graphics.

## 2. Understanding Angles
An angle is formed when two rays share a common starting point called the **vertex**.
In elementary trigonometry, we measure angles in **degrees (°)**, where a full turn is $360^\circ$.

### Angle Classifications:
1. **Acute Angle**: Greater than $0^\circ$ and strictly less than $90^\circ$ ($0^\circ < \theta < 90^\circ$).
2. **Right Angle**: Exactly $90^\circ$ ($\theta = 90^\circ$), represented by a square symbol $\llcorner$.
3. **Obtuse Angle**: Greater than $90^\circ$ and strictly less than $180^\circ$ ($90^\circ < \theta < 180^\circ$).
4. **Straight Angle**: Exactly $180^\circ$ ($\theta = 180^\circ$), forming a straight line.
5. **Reflex Angle**: Greater than $180^\circ$ and strictly less than $360^\circ$.

## 3. Worked Example
**Problem:** In a compass rose, what angle corresponds to North-East if North is $0^\circ$ and East is $90^\circ$? What kind of angle is this?
**Solution:**
$$\theta = \frac{0^\circ + 90^\circ}{2} = 45^\circ$$
Since $0^\circ < 45^\circ < 90^\circ$, this is an **acute angle**.

## 4. Practice Guidance
- Always visualize angle orientation from the horizontal vertex.
- Remember that complementary angles add up to $90^\circ$, while supplementary angles sum to $180^\circ$.

## 5. Summary
Trigonometry enables us to infer inaccessible lengths and angles through known geometrical properties. Accurate angle identification is your initial step.
MARKDOWN,
                        'resources' => [
                            [
                                'type' => 'cheat_sheet',
                                'title' => 'Angle Classification Reference',
                                'content_url' => 'https://example.com/angles-cheat-sheet.pdf',
                            ]
                        ]
                    ],
                    [
                        'title' => 'Types and Properties of Triangles',
                        'slug' => 'types-and-properties-of-triangles',
                        'summary' => 'Comprehensive categorization of triangles by side lengths and angles, along with interior angle sum theorem.',
                        'learning_objectives' => [
                            'Classify triangles by sides: Equilateral, Isosceles, Scalene.',
                            'Classify triangles by interior angles: Acute, Right, Obtuse.',
                            'Prove and apply the interior angle sum theorem ($\sum = 180^\circ$).',
                        ],
                        'estimated_minutes' => 25,
                        'content' => <<<'MARKDOWN'
# Types and Properties of Triangles

## 1. Triangle Classification by Sides
- **Equilateral Triangle**: All 3 sides are equal in length; all 3 interior angles are equal ($60^\circ$ each).
- **Isosceles Triangle**: Exactly 2 sides are equal; the angles opposite to the equal sides are also equal (base angles).
- **Scalene Triangle**: All 3 sides have different lengths; all 3 angles are different.

## 2. Triangle Classification by Angles
- **Acute-Angled Triangle**: All 3 interior angles are acute ($< 90^\circ$).
- **Right-Angled Triangle**: Contains exactly one $90^\circ$ angle. The other two angles are acute and complementary ($\alpha + \beta = 90^\circ$).
- **Obtuse-Angled Triangle**: Contains exactly one obtuse angle ($> 90^\circ$).

## 3. Fundamental Theorem: Triangle Angle Sum
For **any** Euclidean planar triangle with interior angles $A$, $B$, and $C$:
$$A + B + C = 180^\circ$$

### Worked Example:
**Problem:** A triangle has two known angles measuring $42^\circ$ and $68^\circ$. Calculate the measure of the third angle $C$ and classify the triangle by its angles.
**Step-by-step Solution:**
$$A + B + C = 180^\circ$$
$$42^\circ + 68^\circ + C = 180^\circ$$
$$110^\circ + C = 180^\circ \implies C = 70^\circ$$
All three angles are $42^\circ, 68^\circ, 70^\circ$.
Since every angle is strictly less than $90^\circ$, this is an **Acute-Angled Triangle**.

## 4. Lesson Summary
Every triangle's interior angles sum to $180^\circ$. A right-angled triangle has one $90^\circ$ angle, which makes its remaining two acute angles sum to $90^\circ$.
MARKDOWN,
                    ]
                ],
                'assessment' => [
                    'title' => 'Week 1 Assessment: Foundations and Triangle Properties',
                    'description' => 'Test your understanding of angle classifications and triangle angle summation rules.',
                    'duration_minutes' => 25,
                    'questions' => [
                        [
                            'question_text' => 'What is the sum of interior angles in any planar triangle?',
                            'question_type' => 'multiple_choice',
                            'explanation' => 'The sum of all three interior angles in any Euclidean planar triangle is always exactly 180 degrees.',
                            'marks' => 1.00,
                            'options' => [
                                ['text' => '90°', 'is_correct' => false],
                                ['text' => '180°', 'is_correct' => true],
                                ['text' => '270°', 'is_correct' => false],
                                ['text' => '360°', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'An angle measuring 75° is classified as which type of angle?',
                            'question_type' => 'multiple_choice',
                            'explanation' => 'An acute angle is strictly greater than 0° and less than 90°. 75° falls in this range.',
                            'marks' => 1.00,
                            'options' => [
                                ['text' => 'Acute angle', 'is_correct' => true],
                                ['text' => 'Obtuse angle', 'is_correct' => false],
                                ['text' => 'Right angle', 'is_correct' => false],
                                ['text' => 'Reflex angle', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'In a right-angled triangle, if one acute angle is 35°, what is the measure of the other acute angle?',
                            'question_type' => 'numerical',
                            'explanation' => 'The two acute angles in a right-angled triangle are complementary: 90° - 35° = 55°.',
                            'marks' => 1.00,
                            'metadata' => ['correct_value' => 55, 'tolerance' => 0],
                            'options' => [
                                ['text' => '55', 'is_correct' => true],
                                ['text' => '45', 'is_correct' => false],
                                ['text' => '65', 'is_correct' => false],
                                ['text' => '145', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'True or False: An equilateral triangle always has all three angles equal to 60°.',
                            'question_type' => 'true_false',
                            'explanation' => 'Since all three sides are equal, all three angles must be equal: 180° / 3 = 60°.',
                            'marks' => 1.00,
                            'options' => [
                                ['text' => 'True', 'is_correct' => true],
                                ['text' => 'False', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'A triangle with side lengths 7 cm, 7 cm, and 10 cm is classified as:',
                            'question_type' => 'multiple_choice',
                            'explanation' => 'A triangle having exactly two equal sides is an isosceles triangle.',
                            'marks' => 1.00,
                            'options' => [
                                ['text' => 'Equilateral', 'is_correct' => false],
                                ['text' => 'Isosceles', 'is_correct' => true],
                                ['text' => 'Scalene', 'is_correct' => false],
                                ['text' => 'Right', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'Can a triangle possess two right angles?',
                            'question_type' => 'true_false',
                            'explanation' => 'No; two 90° angles would already total 180°, leaving 0° for the third angle, which is impossible in planar Euclidean geometry.',
                            'marks' => 1.00,
                            'options' => [
                                ['text' => 'True', 'is_correct' => false],
                                ['text' => 'False', 'is_correct' => true],
                            ]
                        ],
                        [
                            'question_text' => 'An angle of 135° is classified as:',
                            'question_type' => 'multiple_choice',
                            'explanation' => 'An angle between 90° and 180° is an obtuse angle.',
                            'marks' => 1.00,
                            'options' => [
                                ['text' => 'Acute', 'is_correct' => false],
                                ['text' => 'Right', 'is_correct' => false],
                                ['text' => 'Obtuse', 'is_correct' => true],
                                ['text' => 'Straight', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'If two angles of a triangle are 50° and 60°, what is the third angle in degrees?',
                            'question_type' => 'numerical',
                            'explanation' => '180 - (50 + 60) = 180 - 110 = 70 degrees.',
                            'marks' => 1.00,
                            'metadata' => ['correct_value' => 70, 'tolerance' => 0],
                            'options' => [
                                ['text' => '70', 'is_correct' => true],
                                ['text' => '80', 'is_correct' => false],
                                ['text' => '60', 'is_correct' => false],
                                ['text' => '90', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'What Greek word root does "gon" in trigonometry come from?',
                            'question_type' => 'multiple_choice',
                            'explanation' => 'Trigonon stems from tri (three) and gonia (angle/corner).',
                            'marks' => 1.00,
                            'options' => [
                                ['text' => 'Circle', 'is_correct' => false],
                                ['text' => 'Corner / Angle', 'is_correct' => true],
                                ['text' => 'Distance', 'is_correct' => false],
                                ['text' => 'Number', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'True or False: In a scalene triangle, all three interior angles have distinct measures.',
                            'question_type' => 'true_false',
                            'explanation' => 'Yes, because all three sides are distinct, all three opposing angles must also have distinct measures.',
                            'marks' => 1.00,
                            'options' => [
                                ['text' => 'True', 'is_correct' => true],
                                ['text' => 'False', 'is_correct' => false],
                            ]
                        ],
                    ]
                ]
            ],

            // ==========================================
            // WEEK 2: RIGHT-ANGLED TRIANGLES
            // ==========================================
            [
                'week_number' => 2,
                'title' => 'Right-Angled Triangles and Side Identification',
                'slug' => 'week-2-right-angled-triangles',
                'description' => 'Master the geometry of right-angled triangles: identifying the hypotenuse, opposite, and adjacent sides with respect to any chosen reference angle.',
                'learning_objectives' => [
                    'Locate the right angle and opposite hypotenuse with 100% accuracy.',
                    'Identify the opposite side relative to a designated acute angle $\theta$.',
                    'Identify the adjacent side relative to a designated acute angle $\theta$.',
                    'Observe how switching the reference angle swaps the opposite and adjacent labels.',
                ],
                'lessons' => [
                    [
                        'title' => 'The Anatomy of a Right Triangle & The Hypotenuse',
                        'slug' => 'anatomy-of-a-right-triangle-and-the-hypotenuse',
                        'summary' => 'Understanding the unique properties of right-angled triangles and identifying the hypotenuse.',
                        'learning_objectives' => [
                            'Identify the right angle symbol ($\llcorner$).',
                            'Define the hypotenuse as the side opposite the $90^\circ$ angle.',
                            'Recognize the hypotenuse as always the longest side of a right triangle.',
                        ],
                        'estimated_minutes' => 20,
                        'content' => <<<'MARKDOWN'
# The Anatomy of a Right Triangle

## 1. The Right Angle ($90^\circ$)
In a right-angled triangle, one angle is exactly $90^\circ$. This angle is universally designated by a square marker at the corner vertex.

## 2. The Hypotenuse
The **Hypotenuse** is defined by two steadfast geometric rules:
1. It is directly opposite the $90^\circ$ right angle.
2. It is always strictly the **longest side** in the right-angled triangle.

> **Key Rule**: The hypotenuse NEVER touches the right angle marker. It faces directly across from it.

### Worked Example:
In $\triangle ABC$, the right angle is located at vertex $C$.
- The side facing vertex $C$ is side $AB$.
- Therefore, $AB$ is the **hypotenuse**.

## 3. Practice Check
Look at any triangle orientation, even rotated upside down: find the square box $\llcorner$, draw an arrow pointing straight out from that vertex: the side you hit is always the hypotenuse!
MARKDOWN,
                    ],
                    [
                        'title' => 'Opposite and Adjacent Sides Relative to Reference Angle',
                        'slug' => 'opposite-and-adjacent-sides-relative-to-reference-angle',
                        'summary' => 'Mastering the positional relationship of triangle legs with respect to chosen reference angle theta.',
                        'learning_objectives' => [
                            'Define the reference angle $\theta$.',
                            'Locate the Opposite side relative to $\theta$.',
                            'Locate the Adjacent side relative to $\theta$.',
                            'Analyze the effect of shifting reference angles.',
                        ],
                        'estimated_minutes' => 25,
                        'content' => <<<'MARKDOWN'
# Opposite and Adjacent Sides

## 1. The Reference Angle ($\theta$)
To calculate trigonometric ratios, we must select one of the two acute angles in the right triangle as our **reference angle**, commonly symbolized by Greek letters such as $\theta$ (theta) or $\phi$ (phi).

## 2. Defining the Sides Relative to $\theta$:
1. **Hypotenuse ($H$)**: Always opposite the $90^\circ$ angle. (Independent of $\theta$).
2. **Opposite Side ($O$)**: The side across from the reference angle $\theta$. It does not touch angle $\theta$.
3. **Adjacent Side ($A$)**: The leg next to the reference angle $\theta$. It lies *between* angle $\theta$ and the $90^\circ$ right angle.

## 3. Crucial Rule: The Swapping Effect
If you switch your reference angle from acute angle $\angle A$ to acute angle $\angle B$:
- The **Hypotenuse** remains unchanged.
- The side that was **Opposite** $\angle A$ becomes **Adjacent** to $\angle B$.
- The side that was **Adjacent** to $\angle A$ becomes **Opposite** to $\angle B$.

### Worked Example:
Given $\triangle XYZ$ with $\angle Y = 90^\circ$:
- Side $XZ$ is opposite $\angle Y$, so $XZ$ is the **Hypotenuse**.
- For reference angle $\angle X$:
  - Side $YZ$ is the **Opposite** side.
  - Side $XY$ is the **Adjacent** side.
- For reference angle $\angle Z$:
  - Side $XY$ is the **Opposite** side.
  - Side $YZ$ is the **Adjacent** side.

## 4. Summary Table
| Side Label | Definition relative to $\theta$ |
| :--- | :--- |
| **Hypotenuse (H)** | Directly opposite $90^\circ$ angle; longest side. |
| **Opposite (O)** | Facing straight across from $\theta$; does not touch $\theta$. |
| **Adjacent (A)** | Forms one arm of angle $\theta$; connects $\theta$ to the $90^\circ$ vertex. |
MARKDOWN,
                    ]
                ],
                'assessment' => [
                    'title' => 'Week 2 Assessment: Right-Angled Triangles and Side Identification',
                    'description' => 'Test your ability to label Hypotenuse, Opposite, and Adjacent sides accurately.',
                    'duration_minutes' => 25,
                    'questions' => [
                        [
                            'question_text' => 'Which side in a right-angled triangle is always directly opposite the 90° angle?',
                            'question_type' => 'multiple_choice',
                            'explanation' => 'The side opposite the 90° right angle is universally defined as the hypotenuse.',
                            'marks' => 1.00,
                            'options' => [
                                ['text' => 'Hypotenuse', 'is_correct' => true],
                                ['text' => 'Opposite', 'is_correct' => false],
                                ['text' => 'Adjacent', 'is_correct' => false],
                                ['text' => 'Altitude', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'True or False: The hypotenuse is always the longest side of a right-angled triangle.',
                            'question_type' => 'true_false',
                            'explanation' => 'True. Because 90° is the largest possible angle in a right triangle, the side opposite to it is always the longest.',
                            'marks' => 1.00,
                            'options' => [
                                ['text' => 'True', 'is_correct' => true],
                                ['text' => 'False', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'In right triangle ABC with right angle at C, which side is adjacent to angle A?',
                            'question_type' => 'multiple_choice',
                            'explanation' => 'The hypotenuse is AB. The side opposite angle A is BC. The side adjacent to angle A (between A and C) is AC.',
                            'marks' => 1.00,
                            'options' => [
                                ['text' => 'Side AC', 'is_correct' => true],
                                ['text' => 'Side BC', 'is_correct' => false],
                                ['text' => 'Side AB', 'is_correct' => false],
                                ['text' => 'None of these', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'In right triangle ABC with right angle at C, which side is opposite to angle A?',
                            'question_type' => 'multiple_choice',
                            'explanation' => 'Facing directly across from angle A is side BC.',
                            'marks' => 1.00,
                            'options' => [
                                ['text' => 'Side BC', 'is_correct' => true],
                                ['text' => 'Side AC', 'is_correct' => false],
                                ['text' => 'Side AB', 'is_correct' => false],
                                ['text' => 'Side C', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'If you change your reference angle from one acute angle to the other, what happens to the hypotenuse?',
                            'question_type' => 'multiple_choice',
                            'explanation' => 'The hypotenuse always remains opposite the 90° angle, so changing the reference angle does not affect it.',
                            'marks' => 1.00,
                            'options' => [
                                ['text' => 'It becomes the opposite side', 'is_correct' => false],
                                ['text' => 'It becomes the adjacent side', 'is_correct' => false],
                                ['text' => 'It remains the hypotenuse', 'is_correct' => true],
                                ['text' => 'Its length doubles', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'True or False: The adjacent side connects the reference angle directly to the right-angle vertex.',
                            'question_type' => 'true_false',
                            'explanation' => 'True. The adjacent leg forms the border between the reference angle vertex and the 90° right angle vertex.',
                            'marks' => 1.00,
                            'options' => [
                                ['text' => 'True', 'is_correct' => true],
                                ['text' => 'False', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'In triangle PQR with right angle at Q, if angle P = 30°, what is the measure of angle R in degrees?',
                            'question_type' => 'numerical',
                            'explanation' => '90 - 30 = 60 degrees.',
                            'marks' => 1.00,
                            'metadata' => ['correct_value' => 60, 'tolerance' => 0],
                            'options' => [
                                ['text' => '60', 'is_correct' => true],
                                ['text' => '30', 'is_correct' => false],
                                ['text' => '45', 'is_correct' => false],
                                ['text' => '90', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'Can the opposite side to an acute angle in a right triangle be equal in length to the adjacent side?',
                            'question_type' => 'true_false',
                            'explanation' => 'Yes! In a 45°-45°-90° isosceles right triangle, opposite and adjacent sides have identical lengths.',
                            'marks' => 1.00,
                            'options' => [
                                ['text' => 'True', 'is_correct' => true],
                                ['text' => 'False', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'Can the hypotenuse ever be shorter than the opposite side in a planar right triangle?',
                            'question_type' => 'true_false',
                            'explanation' => 'No; the hypotenuse is strictly the longest side in any right triangle.',
                            'marks' => 1.00,
                            'options' => [
                                ['text' => 'True', 'is_correct' => false],
                                ['text' => 'False', 'is_correct' => true],
                            ]
                        ],
                        [
                            'question_text' => 'What is the Greek letter commonly used to represent an unknown angle in trigonometry?',
                            'question_type' => 'multiple_choice',
                            'explanation' => 'Theta (θ) is the standard Greek letter used for angle variables.',
                            'marks' => 1.00,
                            'options' => [
                                ['text' => 'Theta (θ)', 'is_correct' => true],
                                ['text' => 'Sigma (σ)', 'is_correct' => false],
                                ['text' => 'Delta (Δ)', 'is_correct' => false],
                                ['text' => 'Lambda (λ)', 'is_correct' => false],
                            ]
                        ],
                    ]
                ]
            ],

            // ==========================================
            // WEEK 3: PYTHAGORAS' THEOREM
            // ==========================================
            [
                'week_number' => 3,
                'title' => "Pythagoras' Theorem",
                'slug' => 'week-3-pythagoras-theorem',
                'description' => 'Delve into the geometric relationship connecting the three sides of any right-angled triangle through a² + b² = c².',
                'learning_objectives' => [
                    'State Pythagoras\' Theorem in words and algebraic formula.',
                    'Calculate the hypotenuse length given the lengths of two legs.',
                    'Calculate a leg length given the hypotenuse and the other leg.',
                    'Apply Pythagoras\' Theorem to real-world geometric problems.',
                ],
                'lessons' => [
                    [
                        'title' => "Meaning and Formula of Pythagoras' Theorem",
                        'slug' => 'meaning-and-formula-of-pythagoras-theorem',
                        'summary' => 'Introduction to the theorem, geometric square visualization, and the core equation a^2 + b^2 = c^2.',
                        'learning_objectives' => [
                            'Formulate $a^2 + b^2 = c^2$ where $c$ is the hypotenuse.',
                            'Recognize standard Pythagorean triples such as (3, 4, 5) and (5, 12, 13).',
                        ],
                        'estimated_minutes' => 20,
                        'content' => <<<'MARKDOWN'
# Pythagoras' Theorem

## 1. Statement of the Theorem
In any right-angled triangle, the area of the square whose side is the hypotenuse is equal to the sum of the areas of the squares on the other two sides.

Algebraically:
$$a^2 + b^2 = c^2$$
Where:
- $a$ and $b$ are the lengths of the two shorter legs (perpendicular sides).
- $c$ is the length of the **hypotenuse**.

## 2. Solving for the Hypotenuse ($c$)
$$c = \sqrt{a^2 + b^2}$$

### Worked Example:
Find the hypotenuse $c$ of a right triangle with legs $a = 3$ and $b = 4$.
$$c^2 = 3^2 + 4^2 = 9 + 16 = 25$$
$$c = \sqrt{25} = 5$$
The triplet $(3, 4, 5)$ is the most famous **Pythagorean triple**.
MARKDOWN,
                    ],
                    [
                        'title' => 'Finding Unknown Legs and Practical Applications',
                        'slug' => 'finding-unknown-legs-and-practical-applications',
                        'summary' => 'Rearranging the formula to calculate an unknown leg, with word problems involving ladders, ramps, and distances.',
                        'learning_objectives' => [
                            'Rearrange the equation: $a = \sqrt{c^2 - b^2}$.',
                            'Solve word problems using correct square root operations.',
                        ],
                        'estimated_minutes' => 25,
                        'content' => <<<'MARKDOWN'
# Finding Unknown Legs

## 1. Rearranging the Formula
When the hypotenuse $c$ is already known along with one leg $b$, we subtract to find leg $a$:
$$a^2 = c^2 - b^2 \implies a = \sqrt{c^2 - b^2}$$
$$b^2 = c^2 - a^2 \implies b = \sqrt{c^2 - a^2}$$

> **Critical Warning**: Never add squares when searching for a shorter leg! You only add when finding the hypotenuse $c$.

## 2. Worked Example: Ladder Problem
A 13-meter ladder leans against a vertical wall. The base of the ladder is placed 5 meters away from the wall. How high up the wall does the ladder reach?

**Step 1:** Model as a right triangle.
- Hypotenuse (ladder) $c = 13\text{ m}$.
- Ground distance $b = 5\text{ m}$.
- Wall height $a = ?$

**Step 2:** Apply subtraction formula:
$$a = \sqrt{c^2 - b^2} = \sqrt{13^2 - 5^2} = \sqrt{169 - 25} = \sqrt{144} = 12\text{ m}$$
The ladder reaches **12 meters** up the wall.
MARKDOWN,
                    ]
                ],
                'assessment' => [
                    'title' => "Week 3 Assessment: Pythagoras' Theorem",
                    'description' => 'Evaluate your competence in finding unknown sides and solving Pythagorean applications.',
                    'duration_minutes' => 30,
                    'questions' => [
                        [
                            'question_text' => 'In a right triangle with legs of length 6 cm and 8 cm, what is the length of the hypotenuse in cm?',
                            'question_type' => 'numerical',
                            'explanation' => 'c = sqrt(6^2 + 8^2) = sqrt(36 + 64) = sqrt(100) = 10 cm.',
                            'marks' => 1.00,
                            'metadata' => ['correct_value' => 10, 'tolerance' => 0],
                            'options' => [
                                ['text' => '10', 'is_correct' => true],
                                ['text' => '14', 'is_correct' => false],
                                ['text' => '12', 'is_correct' => false],
                                ['text' => '48', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'If the hypotenuse is 15 cm and one leg is 9 cm, what is the length of the other leg in cm?',
                            'question_type' => 'numerical',
                            'explanation' => 'b = sqrt(15^2 - 9^2) = sqrt(225 - 81) = sqrt(144) = 12 cm.',
                            'marks' => 1.00,
                            'metadata' => ['correct_value' => 12, 'tolerance' => 0],
                            'options' => [
                                ['text' => '12', 'is_correct' => true],
                                ['text' => '10', 'is_correct' => false],
                                ['text' => '6', 'is_correct' => false],
                                ['text' => '24', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'Which of the following forms a valid Pythagorean Triple?',
                            'question_type' => 'multiple_choice',
                            'explanation' => '5^2 + 12^2 = 25 + 144 = 169 = 13^2. Hence (5, 12, 13) is a Pythagorean triple.',
                            'marks' => 1.00,
                            'options' => [
                                ['text' => '3, 5, 7', 'is_correct' => false],
                                ['text' => '5, 12, 13', 'is_correct' => true],
                                ['text' => '4, 6, 8', 'is_correct' => false],
                                ['text' => '7, 9, 11', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'True or False: To find the length of an unknown shorter leg, you add the squares of the hypotenuse and the other leg.',
                            'question_type' => 'true_false',
                            'explanation' => 'False! You subtract the square of the known leg from the square of the hypotenuse: a^2 = c^2 - b^2.',
                            'marks' => 1.00,
                            'options' => [
                                ['text' => 'True', 'is_correct' => false],
                                ['text' => 'False', 'is_correct' => true],
                            ]
                        ],
                        [
                            'question_text' => 'A TV screen diagonal is 25 inches and its width is 20 inches. What is its height in inches?',
                            'question_type' => 'numerical',
                            'explanation' => 'Height = sqrt(25^2 - 20^2) = sqrt(625 - 400) = sqrt(225) = 15 inches.',
                            'marks' => 1.00,
                            'metadata' => ['correct_value' => 15, 'tolerance' => 0],
                            'options' => [
                                ['text' => '15', 'is_correct' => true],
                                ['text' => '12', 'is_correct' => false],
                                ['text' => '18', 'is_correct' => false],
                                ['text' => '10', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'If a right triangle has legs of length 1 and 1, what is the length of its hypotenuse?',
                            'question_type' => 'multiple_choice',
                            'explanation' => 'c = sqrt(1^2 + 1^2) = sqrt(2) ≈ 1.414.',
                            'marks' => 1.00,
                            'options' => [
                                ['text' => '√2', 'is_correct' => true],
                                ['text' => '2', 'is_correct' => false],
                                ['text' => '1', 'is_correct' => false],
                                ['text' => '√3', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'Can Pythagoras theorem be directly applied to calculate sides of an obtuse-angled triangle without modification?',
                            'question_type' => 'true_false',
                            'explanation' => 'No; the theorem a^2 + b^2 = c^2 is strictly valid only for right-angled triangles in planar geometry.',
                            'marks' => 1.00,
                            'options' => [
                                ['text' => 'True', 'is_correct' => false],
                                ['text' => 'False', 'is_correct' => true],
                            ]
                        ],
                        [
                            'question_text' => 'What is the hypotenuse of a right triangle with legs a = 8 and b = 15?',
                            'question_type' => 'numerical',
                            'explanation' => 'c = sqrt(8^2 + 15^2) = sqrt(64 + 225) = sqrt(289) = 17.',
                            'marks' => 1.00,
                            'metadata' => ['correct_value' => 17, 'tolerance' => 0],
                            'options' => [
                                ['text' => '17', 'is_correct' => true],
                                ['text' => '19', 'is_correct' => false],
                                ['text' => '23', 'is_correct' => false],
                                ['text' => '16', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'If a triangle has sides 9, 12, and 15, is it a right-angled triangle?',
                            'question_type' => 'true_false',
                            'explanation' => '9^2 + 12^2 = 81 + 144 = 225 = 15^2. Since the converse of Pythagoras holds, it is a right-angled triangle.',
                            'marks' => 1.00,
                            'options' => [
                                ['text' => 'True', 'is_correct' => true],
                                ['text' => 'False', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'A ship sails 24 km North, then 7 km East. What is its direct straight-line distance from the starting point in km?',
                            'question_type' => 'numerical',
                            'explanation' => 'd = sqrt(24^2 + 7^2) = sqrt(576 + 49) = sqrt(625) = 25 km.',
                            'marks' => 1.00,
                            'metadata' => ['correct_value' => 25, 'tolerance' => 0],
                            'options' => [
                                ['text' => '25', 'is_correct' => true],
                                ['text' => '31', 'is_correct' => false],
                                ['text' => '26', 'is_correct' => false],
                                ['text' => '22', 'is_correct' => false],
                            ]
                        ],
                    ]
                ]
            ],

            // ==========================================
            // WEEK 4: TRIGONOMETRIC RATIOS
            // ==========================================
            [
                'week_number' => 4,
                'title' => 'Trigonometric Ratios: SOH-CAH-TOA',
                'slug' => 'week-4-trigonometric-ratios',
                'description' => 'Understand the fundamental ratios connecting angle measure and side lengths: Sine, Cosine, and Tangent.',
                'learning_objectives' => [
                    'Define Sine as $\frac{\text{Opposite}}{\text{Hypotenuse}}$.',
                    'Define Cosine as $\frac{\text{Adjacent}}{\text{Hypotenuse}}$.',
                    'Define Tangent as $\frac{\text{Opposite}}{\text{Adjacent}}$.',
                    'Memorize and apply the mnemonic SOH-CAH-TOA.',
                    'Select the correct trigonometric ratio for a given problem.',
                ],
                'lessons' => [
                    [
                        'title' => 'Defining Sine, Cosine, and Tangent',
                        'slug' => 'defining-sine-cosine-and-tangent',
                        'summary' => 'Mathematical definitions of the primary trigonometric ratios and their geometric meaning.',
                        'learning_objectives' => [
                            'Write the ratio formulas for $\sin\theta$, $\cos\theta$, and $\tan\theta$.',
                            'Recognize that these ratios are dimensionless scale factors.',
                        ],
                        'estimated_minutes' => 20,
                        'content' => <<<'MARKDOWN'
# Trigonometric Ratios

## 1. The Three Primary Ratios
For any right-angled triangle with reference angle $\theta$:
1. **Sine ($\sin\theta$)**:
   $$\sin\theta = \frac{\text{Opposite}}{\text{Hypotenuse}}$$
2. **Cosine ($\cos\theta$)**:
   $$\cos\theta = \frac{\text{Adjacent}}{\text{Hypotenuse}}$$
3. **Tangent ($\tan\theta$)**:
   $$\tan\theta = \frac{\text{Opposite}}{\text{Adjacent}} = \frac{\sin\theta}{\cos\theta}$$

## 2. Crucial Value Bounds
Because the Hypotenuse is strictly the longest side in a right triangle:
- $0 < \sin\theta < 1$ for acute $\theta$ ($0^\circ < \theta < 90^\circ$).
- $0 < \cos\theta < 1$ for acute $\theta$.
- However, $\tan\theta$ can take any positive value from $0$ to $+\infty$!
MARKDOWN,
                    ],
                    [
                        'title' => 'The SOH-CAH-TOA Mnemonic & Ratio Selection',
                        'slug' => 'the-soh-cah-toa-mnemonic-and-ratio-selection',
                        'summary' => 'Systematic framework for picking the exact ratio required based on given and target quantities.',
                        'learning_objectives' => [
                            'Use SOH-CAH-TOA to recall formulas effortlessly.',
                            'Follow the 3-step decision rule for selecting the right ratio.',
                        ],
                        'estimated_minutes' => 25,
                        'content' => <<<'MARKDOWN'
# The SOH-CAH-TOA Mnemonic

## 1. The Mnemonic Breakdown
- **SOH**: **S**ine = **O**pposite / **H**ypotenuse
- **CAH**: **C**osine = **A**djacent / **H**ypotenuse
- **TOA**: **T**angent = **O**pposite / **A**djacent

## 2. The 3-Step Selection Framework
When tackling any trigonometry problem:
1. **Label the three sides** with respect to the given angle: $O$, $A$, $H$.
2. **Circle the two active sides**: the side you **know** and the side you **need to find**.
3. **Match with SOH-CAH-TOA**:
   - Involving $O$ and $H$ $\implies$ Pick **SOH** ($\sin$).
   - Involving $A$ and $H$ $\implies$ Pick **CAH** ($\cos$).
   - Involving $O$ and $A$ $\implies$ Pick **TOA** ($\tan$).

### Worked Example:
In a right triangle, angle $\theta = 30^\circ$, hypotenuse $H = 10$, and we need to find the opposite side $O$.
- Active sides: $O$ and $H$.
- Choice: $\sin\theta = \frac{O}{H} \implies \sin(30^\circ) = \frac{O}{10}$.
- Since $\sin(30^\circ) = 0.5$, $O = 10 \times 0.5 = 5$.
MARKDOWN,
                    ]
                ],
                'assessment' => [
                    'title' => 'Week 4 Assessment: Trigonometric Ratios (SOH-CAH-TOA)',
                    'description' => 'Test your mastery of Sine, Cosine, Tangent definitions and ratio selection rules.',
                    'duration_minutes' => 25,
                    'questions' => [
                        [
                            'question_text' => 'What is the definition of Sine of an angle in a right triangle?',
                            'question_type' => 'multiple_choice',
                            'explanation' => 'Sine is Opposite divided by Hypotenuse (SOH).',
                            'marks' => 1.00,
                            'options' => [
                                ['text' => 'Opposite / Hypotenuse', 'is_correct' => true],
                                ['text' => 'Adjacent / Hypotenuse', 'is_correct' => false],
                                ['text' => 'Opposite / Adjacent', 'is_correct' => false],
                                ['text' => 'Hypotenuse / Opposite', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'In SOH-CAH-TOA, what does "CAH" stand for?',
                            'question_type' => 'multiple_choice',
                            'explanation' => 'CAH stands for Cosine = Adjacent / Hypotenuse.',
                            'marks' => 1.00,
                            'options' => [
                                ['text' => 'Cosine = Adjacent / Hypotenuse', 'is_correct' => true],
                                ['text' => 'Cosine = Altitude / Height', 'is_correct' => false],
                                ['text' => 'Cosine = Angle / Hypotenuse', 'is_correct' => false],
                                ['text' => 'Cotangent = Area / Height', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'In a 3-4-5 right triangle where opposite side is 3 and adjacent is 4, what is tan(θ)?',
                            'question_type' => 'multiple_choice',
                            'explanation' => 'tan(θ) = Opposite / Adjacent = 3/4 = 0.75.',
                            'marks' => 1.00,
                            'options' => [
                                ['text' => '3/4 (0.75)', 'is_correct' => true],
                                ['text' => '3/5 (0.6)', 'is_correct' => false],
                                ['text' => '4/5 (0.8)', 'is_correct' => false],
                                ['text' => '4/3 (1.33)', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'True or False: For an acute angle in a right triangle, sin(θ) can never exceed 1.',
                            'question_type' => 'true_false',
                            'explanation' => 'True. Since the hypotenuse is the longest side, Opposite / Hypotenuse is always strictly less than 1 for acute angles.',
                            'marks' => 1.00,
                            'options' => [
                                ['text' => 'True', 'is_correct' => true],
                                ['text' => 'False', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'Which ratio should you use if you know the Adjacent side and need to calculate the Opposite side?',
                            'question_type' => 'multiple_choice',
                            'explanation' => 'The ratio connecting Opposite and Adjacent is Tangent (TOA).',
                            'marks' => 1.00,
                            'options' => [
                                ['text' => 'Tangent', 'is_correct' => true],
                                ['text' => 'Sine', 'is_correct' => false],
                                ['text' => 'Cosine', 'is_correct' => false],
                                ['text' => 'None of these', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'In a right triangle, if the opposite side is 6 and the hypotenuse is 10, what is sin(θ)?',
                            'question_type' => 'multiple_choice',
                            'explanation' => 'sin(θ) = 6 / 10 = 0.6.',
                            'marks' => 1.00,
                            'options' => [
                                ['text' => '0.6', 'is_correct' => true],
                                ['text' => '0.8', 'is_correct' => false],
                                ['text' => '0.75', 'is_correct' => false],
                                ['text' => '1.67', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'What is the relationship between tan(θ), sin(θ), and cos(θ)?',
                            'question_type' => 'multiple_choice',
                            'explanation' => 'tan(θ) = sin(θ) / cos(θ).',
                            'marks' => 1.00,
                            'options' => [
                                ['text' => 'tan(θ) = sin(θ) / cos(θ)', 'is_correct' => true],
                                ['text' => 'tan(θ) = cos(θ) / sin(θ)', 'is_correct' => false],
                                ['text' => 'tan(θ) = sin(θ) * cos(θ)', 'is_correct' => false],
                                ['text' => 'tan(θ) = sin(θ) + cos(θ)', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'What is the exact value of sin(30°)?',
                            'question_type' => 'multiple_choice',
                            'explanation' => 'sin(30°) is 1/2 or 0.5.',
                            'marks' => 1.00,
                            'options' => [
                                ['text' => '0.5 (1/2)', 'is_correct' => true],
                                ['text' => '1.0', 'is_correct' => false],
                                ['text' => '0.707 (√2/2)', 'is_correct' => false],
                                ['text' => '0.866 (√3/2)', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'True or False: In a 45°-45°-90° triangle, tan(45°) equals exactly 1.',
                            'question_type' => 'true_false',
                            'explanation' => 'True! Opposite = Adjacent, so tan(45°) = Opposite / Adjacent = 1.',
                            'marks' => 1.00,
                            'options' => [
                                ['text' => 'True', 'is_correct' => true],
                                ['text' => 'False', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'If cos(θ) = 0.8 and hypotenuse is 20, what is the length of the adjacent side?',
                            'question_type' => 'numerical',
                            'explanation' => 'Adjacent = cos(θ) * Hypotenuse = 0.8 * 20 = 16.',
                            'marks' => 1.00,
                            'metadata' => ['correct_value' => 16, 'tolerance' => 0],
                            'options' => [
                                ['text' => '16', 'is_correct' => true],
                                ['text' => '12', 'is_correct' => false],
                                ['text' => '10', 'is_correct' => false],
                                ['text' => '25', 'is_correct' => false],
                            ]
                        ],
                    ]
                ]
            ],

            // ==========================================
            // WEEK 5: UNKNOWN SIDES
            // ==========================================
            [
                'week_number' => 5,
                'title' => 'Calculating Unknown Sides',
                'slug' => 'week-5-unknown-sides',
                'description' => 'Systematically solve for unknown side lengths in right triangles when given an angle and one side, handling both numerator and denominator algebra.',
                'learning_objectives' => [
                    'Solve when the unknown variable is in the numerator ($x = H \times \sin\theta$).',
                    'Solve when the unknown variable is in the denominator ($x = \frac{O}{\sin\theta}$).',
                    'Apply Sine, Cosine, and Tangent to compute missing dimensions in practical contexts.',
                ],
                'lessons' => [
                    [
                        'title' => 'Solving for Sides in Numerator vs Denominator',
                        'slug' => 'solving-for-sides-in-numerator-vs-denominator',
                        'summary' => 'Algebraic techniques for rearranging trigonometric equations depending on whether x is on top or bottom.',
                        'learning_objectives' => [
                            'Master numerator isolation via multiplication.',
                            'Master denominator isolation via reciprocal swapping.',
                        ],
                        'estimated_minutes' => 20,
                        'content' => <<<'MARKDOWN'
# Solving for Unknown Sides

## Case 1: Unknown in the Numerator (Easy Case)
When the unknown side $x$ is on top:
$$\sin\theta = \frac{x}{H} \implies x = H \cdot \sin\theta$$
$$\tan\theta = \frac{x}{A} \implies x = A \cdot \tan\theta$$

### Example:
Given $\theta = 28^\circ$, $H = 15\text{ m}$. Find opposite side $x$:
$$x = 15 \cdot \sin(28^\circ) \approx 15 \cdot 0.4695 \approx 7.04\text{ m}$$

---

## Case 2: Unknown in the Denominator (The Swap Case)
When the unknown side $x$ is on the bottom:
$$\sin\theta = \frac{O}{x} \implies x = \frac{O}{\sin\theta}$$
$$\cos\theta = \frac{A}{x} \implies x = \frac{A}{\cos\theta}$$

> **Memory Trick**: "Swap the variable with the trig ratio!"

### Example:
Given $\theta = 35^\circ$, $O = 8\text{ cm}$. Find hypotenuse $x$:
$$\sin(35^\circ) = \frac{8}{x} \implies x = \frac{8}{\sin(35^\circ)} \approx \frac{8}{0.5736} \approx 13.95\text{ cm}$$
MARKDOWN,
                    ],
                    [
                        'title' => 'Comprehensive Side Calculation Practice',
                        'slug' => 'comprehensive-side-calculation-practice',
                        'summary' => 'Worked engineering and architectural problems utilizing sine, cosine, and tangent side calculations.',
                        'learning_objectives' => [
                            'Perform multi-step side calculations.',
                            'Round answers to specified decimal places or significant figures.',
                        ],
                        'estimated_minutes' => 25,
                        'content' => <<<'MARKDOWN'
# Practical Side Calculations

## Step-by-Step Procedure
1. Sketch and annotate the triangle.
2. Label $O$, $A$, and $H$.
3. Write the chosen formula from SOH-CAH-TOA.
4. Substitute known numbers.
5. Solve for $x$ (multiply if $x$ is on top; swap if $x$ is on bottom).
6. Verify your answer geometrically (e.g. hypotenuse must be the largest).
MARKDOWN,
                    ]
                ],
                'assessment' => [
                    'title' => 'Week 5 Assessment: Calculating Unknown Sides',
                    'description' => 'Assess your competence in solving for unknown side lengths across all cases.',
                    'duration_minutes' => 30,
                    'questions' => [
                        [
                            'question_text' => 'If sin(30°) = 0.5 and hypotenuse is 18 cm, what is the opposite side in cm?',
                            'question_type' => 'numerical',
                            'explanation' => 'Opposite = Hypotenuse * sin(30°) = 18 * 0.5 = 9 cm.',
                            'marks' => 1.00,
                            'metadata' => ['correct_value' => 9, 'tolerance' => 0.1],
                            'options' => [
                                ['text' => '9', 'is_correct' => true],
                                ['text' => '36', 'is_correct' => false],
                                ['text' => '12', 'is_correct' => false],
                                ['text' => '4.5', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'When the unknown side x is in the denominator (e.g., cos(θ) = 12 / x), how do you solve for x?',
                            'question_type' => 'multiple_choice',
                            'explanation' => 'x = 12 / cos(θ). You swap x with cos(θ).',
                            'marks' => 1.00,
                            'options' => [
                                ['text' => 'x = 12 / cos(θ)', 'is_correct' => true],
                                ['text' => 'x = 12 * cos(θ)', 'is_correct' => false],
                                ['text' => 'x = cos(θ) / 12', 'is_correct' => false],
                                ['text' => 'x = 12 - cos(θ)', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'If tan(45°) = 1 and adjacent side is 14 m, what is the opposite side in meters?',
                            'question_type' => 'numerical',
                            'explanation' => 'Opposite = Adjacent * tan(45°) = 14 * 1 = 14 m.',
                            'marks' => 1.00,
                            'metadata' => ['correct_value' => 14, 'tolerance' => 0.1],
                            'options' => [
                                ['text' => '14', 'is_correct' => true],
                                ['text' => '28', 'is_correct' => false],
                                ['text' => '7', 'is_correct' => false],
                                ['text' => '19.8', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'In a triangle with angle θ = 60°, if cos(60°) = 0.5 and adjacent side = 7, what is the hypotenuse?',
                            'question_type' => 'numerical',
                            'explanation' => 'Hypotenuse = Adjacent / cos(60°) = 7 / 0.5 = 14.',
                            'marks' => 1.00,
                            'metadata' => ['correct_value' => 14, 'tolerance' => 0.1],
                            'options' => [
                                ['text' => '14', 'is_correct' => true],
                                ['text' => '3.5', 'is_correct' => false],
                                ['text' => '7', 'is_correct' => false],
                                ['text' => '21', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'True or False: If you calculate a hypotenuse that is shorter than one of the legs, your algebraic calculation is definitely incorrect.',
                            'question_type' => 'true_false',
                            'explanation' => 'True! By definition, the hypotenuse must strictly be the longest side in any right triangle.',
                            'marks' => 1.00,
                            'options' => [
                                ['text' => 'True', 'is_correct' => true],
                                ['text' => 'False', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'If opposite side is 10 and sin(θ) = 0.2, what is the hypotenuse?',
                            'question_type' => 'numerical',
                            'explanation' => 'Hypotenuse = 10 / 0.2 = 50.',
                            'marks' => 1.00,
                            'metadata' => ['correct_value' => 50, 'tolerance' => 0.1],
                            'options' => [
                                ['text' => '50', 'is_correct' => true],
                                ['text' => '2', 'is_correct' => false],
                                ['text' => '20', 'is_correct' => false],
                                ['text' => '5', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'A ramp has an angle of 10° with the ground. If the horizontal length (adjacent) is 20 m and cos(10°) ≈ 0.985, what is the ramp length (hypotenuse) to the nearest whole meter?',
                            'question_type' => 'numerical',
                            'explanation' => '20 / 0.985 ≈ 20.3 m, which is 20 m to the nearest whole meter.',
                            'marks' => 1.00,
                            'metadata' => ['correct_value' => 20, 'tolerance' => 1],
                            'options' => [
                                ['text' => '20', 'is_correct' => true],
                                ['text' => '25', 'is_correct' => false],
                                ['text' => '15', 'is_correct' => false],
                                ['text' => '30', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'Which trigonometric ratio would you choose if you know the hypotenuse and want to find the adjacent side?',
                            'question_type' => 'multiple_choice',
                            'explanation' => 'Cosine relates adjacent and hypotenuse: cos(θ) = Adjacent / Hypotenuse.',
                            'marks' => 1.00,
                            'options' => [
                                ['text' => 'Cosine', 'is_correct' => true],
                                ['text' => 'Sine', 'is_correct' => false],
                                ['text' => 'Tangent', 'is_correct' => false],
                                ['text' => 'Cotangent', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'True or False: If tan(θ) = 2.5 and adjacent side is 4 cm, the opposite side is 10 cm.',
                            'question_type' => 'true_false',
                            'explanation' => 'Opposite = Adjacent * tan(θ) = 4 * 2.5 = 10 cm. Statement is true.',
                            'marks' => 1.00,
                            'options' => [
                                ['text' => 'True', 'is_correct' => true],
                                ['text' => 'False', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'If sin(θ) = 0.8 and hypotenuse is 25, what is the length of the opposite side?',
                            'question_type' => 'numerical',
                            'explanation' => 'Opposite = 25 * 0.8 = 20.',
                            'marks' => 1.00,
                            'metadata' => ['correct_value' => 20, 'tolerance' => 0.1],
                            'options' => [
                                ['text' => '20', 'is_correct' => true],
                                ['text' => '15', 'is_correct' => false],
                                ['text' => '18', 'is_correct' => false],
                                ['text' => '22', 'is_correct' => false],
                            ]
                        ],
                    ]
                ]
            ],

            // ==========================================
            // WEEK 6: UNKNOWN ANGLES
            // ==========================================
            [
                'week_number' => 6,
                'title' => 'Calculating Unknown Angles and Inverse Functions',
                'slug' => 'week-6-unknown-angles',
                'description' => 'Calculate unknown acute angles using inverse trigonometric functions (arcsin, arccos, arctan) and manage calculator angle modes.',
                'learning_objectives' => [
                    'Differentiate regular trig functions from inverse functions ($\sin^{-1}$, $\cos^{-1}$, $\tan^{-1}$).',
                    'Ensure calculators are set to DEGREE (DEG) mode.',
                    'Calculate unknown angle measures given two side lengths.',
                ],
                'lessons' => [
                    [
                        'title' => 'Inverse Trigonometric Functions',
                        'slug' => 'inverse-trigonometric-functions',
                        'summary' => 'Understanding how inverse sine, cosine, and tangent undo ratio operations to reveal angle measures.',
                        'learning_objectives' => [
                            'Explain that $\sin^{-1}(x)$ means "the angle whose sine is $x$".',
                            'Avoid confusing $\sin^{-1}\theta$ with $\frac{1}{\sin\theta}$.',
                        ],
                        'estimated_minutes' => 20,
                        'content' => <<<'MARKDOWN'
# Inverse Trigonometric Functions

## 1. What Does an Inverse Function Do?
- **Regular function**: Takes an **angle** and outputs a **ratio** of sides:
  $$\sin(30^\circ) = 0.5$$
- **Inverse function**: Takes a **ratio** of sides and outputs the **angle**:
  $$\sin^{-1}(0.5) = 30^\circ$$

Other notations: $\arcsin(x)$, $\arccos(x)$, $\arctan(x)$.

> **Crucial Warning on Notation**:
> $\sin^{-1}(x)$ does NOT mean $(\sin(x))^{-1} = \frac{1}{\sin(x)}$!
> The $-1$ superscript denotes an inverse mathematical mapping, not a reciprocal power.
MARKDOWN,
                    ],
                    [
                        'title' => 'Calculator Modes and Finding Angles',
                        'slug' => 'calculator-modes-and-finding-angles',
                        'summary' => 'Configuring Degree vs Radian mode and executing step-by-step angle finding procedures.',
                        'learning_objectives' => [
                            'Identify calculator mode indicators (DEG vs RAD vs GRAD).',
                            'Calculate angles to one decimal place.',
                        ],
                        'estimated_minutes' => 25,
                        'content' => <<<'MARKDOWN'
# Calculator Modes & Calculating Angles

## 1. DEGREE Mode Check
Scientific calculators support three angle units:
- **DEG (Degrees)**: Full turn = $360^\circ$. (Always use this in high school trigonometry!)
- **RAD (Radians)**: Full turn = $2\pi$.
- **GRAD (Gradians)**: Full turn = $400$.

### Quick Verification Test:
Type $\sin(30)$ on your calculator:
- If the output is **0.5**, your calculator is in **DEG** mode!
- If the output is **-0.988**, your calculator is in **RAD** mode. Switch it immediately!

## 2. Worked Example
Find angle $\theta$ in a right triangle where Opposite $= 7\text{ cm}$ and Adjacent $= 10\text{ cm}$.
$$\tan\theta = \frac{O}{A} = \frac{7}{10} = 0.7$$
$$\theta = \tan^{-1}(0.7) \approx 35.0^\circ$$
MARKDOWN,
                    ]
                ],
                'assessment' => [
                    'title' => 'Week 6 Assessment: Calculating Unknown Angles',
                    'description' => 'Test your understanding of inverse trig functions and angle calculations.',
                    'duration_minutes' => 25,
                    'questions' => [
                        [
                            'question_text' => 'If sin(θ) = 0.5, what is the angle θ in degrees?',
                            'question_type' => 'numerical',
                            'explanation' => 'sin^-1(0.5) = 30°.',
                            'marks' => 1.00,
                            'metadata' => ['correct_value' => 30, 'tolerance' => 0.1],
                            'options' => [
                                ['text' => '30', 'is_correct' => true],
                                ['text' => '45', 'is_correct' => false],
                                ['text' => '60', 'is_correct' => false],
                                ['text' => '90', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'If tan(θ) = 1, what is the angle θ in degrees?',
                            'question_type' => 'numerical',
                            'explanation' => 'tan^-1(1) = 45°.',
                            'marks' => 1.00,
                            'metadata' => ['correct_value' => 45, 'tolerance' => 0.1],
                            'options' => [
                                ['text' => '45', 'is_correct' => true],
                                ['text' => '30', 'is_correct' => false],
                                ['text' => '60', 'is_correct' => false],
                                ['text' => '90', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'True or False: sin^-1(x) is mathematically identical to 1 / sin(x).',
                            'question_type' => 'true_false',
                            'explanation' => 'False! sin^-1(x) is the inverse function (arcsin), while 1/sin(x) is the reciprocal function (cosecant).',
                            'marks' => 1.00,
                            'options' => [
                                ['text' => 'True', 'is_correct' => false],
                                ['text' => 'False', 'is_correct' => true],
                            ]
                        ],
                        [
                            'question_text' => 'What mode must your calculator display when solving elementary trigonometry angle problems in degrees?',
                            'question_type' => 'multiple_choice',
                            'explanation' => 'It must be in Degree mode (DEG).',
                            'marks' => 1.00,
                            'options' => [
                                ['text' => 'DEG (Degree)', 'is_correct' => true],
                                ['text' => 'RAD (Radian)', 'is_correct' => false],
                                ['text' => 'GRAD (Gradian)', 'is_correct' => false],
                                ['text' => 'HEX (Hexadecimal)', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'In a right triangle, Opposite = 5 and Hypotenuse = 10. Which expression finds θ?',
                            'question_type' => 'multiple_choice',
                            'explanation' => 'sin(θ) = 5/10 = 0.5, hence θ = sin^-1(5/10).',
                            'marks' => 1.00,
                            'options' => [
                                ['text' => 'θ = sin^-1(5/10)', 'is_correct' => true],
                                ['text' => 'θ = cos^-1(5/10)', 'is_correct' => false],
                                ['text' => 'θ = tan^-1(10/5)', 'is_correct' => false],
                                ['text' => 'θ = sin(10/5)', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'If cos(θ) = 0.5, what is the angle θ in degrees?',
                            'question_type' => 'numerical',
                            'explanation' => 'cos^-1(0.5) = 60°.',
                            'marks' => 1.00,
                            'metadata' => ['correct_value' => 60, 'tolerance' => 0.1],
                            'options' => [
                                ['text' => '60', 'is_correct' => true],
                                ['text' => '30', 'is_correct' => false],
                                ['text' => '45', 'is_correct' => false],
                                ['text' => '90', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'In a right triangle with adjacent = 8 and hypotenuse = 16, what is angle θ in degrees?',
                            'question_type' => 'numerical',
                            'explanation' => 'cos(θ) = 8/16 = 0.5. θ = cos^-1(0.5) = 60°.',
                            'marks' => 1.00,
                            'metadata' => ['correct_value' => 60, 'tolerance' => 0.1],
                            'options' => [
                                ['text' => '60', 'is_correct' => true],
                                ['text' => '30', 'is_correct' => false],
                                ['text' => '45', 'is_correct' => false],
                                ['text' => '75', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'What is another common name for the inverse tangent function?',
                            'question_type' => 'multiple_choice',
                            'explanation' => 'Arctangent (arctan) is the standard alternative name for tan^-1.',
                            'marks' => 1.00,
                            'options' => [
                                ['text' => 'Arctan', 'is_correct' => true],
                                ['text' => 'Cotan', 'is_correct' => false],
                                ['text' => 'Secant', 'is_correct' => false],
                                ['text' => 'Cosecant', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'Can you compute sin^-1(1.5) on real numbers?',
                            'question_type' => 'true_false',
                            'explanation' => 'No; sine ratio for real angles can never exceed 1. Attempting sin^-1(1.5) produces a Math Domain Error.',
                            'marks' => 1.00,
                            'options' => [
                                ['text' => 'True', 'is_correct' => false],
                                ['text' => 'False', 'is_correct' => true],
                            ]
                        ],
                        [
                            'question_text' => 'If tan(θ) = 0, what is the angle θ in degrees?',
                            'question_type' => 'numerical',
                            'explanation' => 'tan^-1(0) = 0°.',
                            'marks' => 1.00,
                            'metadata' => ['correct_value' => 0, 'tolerance' => 0.1],
                            'options' => [
                                ['text' => '0', 'is_correct' => true],
                                ['text' => '90', 'is_correct' => false],
                                ['text' => '45', 'is_correct' => false],
                                ['text' => '180', 'is_correct' => false],
                            ]
                        ],
                    ]
                ]
            ],

            // ==========================================
            // WEEK 7: ELEVATION AND DEPRESSION
            // ==========================================
            [
                'week_number' => 7,
                'title' => 'Angles of Elevation, Depression, Heights and Distances',
                'slug' => 'week-7-elevation-and-depression',
                'description' => 'Apply trigonometry to real-life observational problems: understanding angles of elevation and depression, horizontal baselines, and heights.',
                'learning_objectives' => [
                    'Define the angle of elevation as measured upward from the horizontal.',
                    'Define the angle of depression as measured downward from the horizontal.',
                    'Recognize the alternate interior angles equality: Angle of Elevation = Angle of Depression.',
                    'Solve multi-step word problems involving building heights, lighthouses, and distances.',
                ],
                'lessons' => [
                    [
                        'title' => 'Understanding Elevation and Depression',
                        'slug' => 'understanding-elevation-and-depression',
                        'summary' => 'Fundamental definitions of elevation and depression with respect to the observer horizontal eye-line.',
                        'learning_objectives' => [
                            'Identify the horizontal line of sight as the $0^\circ$ baseline.',
                            'Relate observer perspectives using alternate interior angles.',
                        ],
                        'estimated_minutes' => 20,
                        'content' => <<<'MARKDOWN'
# Angles of Elevation and Depression

## 1. The Horizontal Line of Sight
Both angles are measured from the observer's **horizontal eye-line**:
- **Angle of Elevation**: The angle between the horizontal line and the line of sight **upwards** to an object above the observer.
- **Angle of Depression**: The angle between the horizontal line and the line of sight **downwards** to an object below the observer.

> **CRITICAL RULE**: The angle of depression is NEVER measured from the vertical line (such as a cliff face or building wall). It is ALWAYS measured from the horizontal.

## 2. Alternate Interior Angles Property
Because the observer's horizontal line and the ground level are parallel lines:
$$\text{Angle of Depression from top} = \text{Angle of Elevation from bottom}$$
This geometric equivalence allows you to seamlessly map depression problems into standard ground-level right triangles!
MARKDOWN,
                    ],
                    [
                        'title' => 'Solving Real-World Heights and Distances Problems',
                        'slug' => 'solving-real-world-heights-and-distances-problems',
                        'summary' => 'Comprehensive worked word problems covering observer height additions and landmark triangulation.',
                        'learning_objectives' => [
                            'Incorporate observer height when required.',
                            'Model complex scenarios involving lighthouses, towers, and aircraft.',
                        ],
                        'estimated_minutes' => 25,
                        'content' => <<<'MARKDOWN'
# Heights and Distances

### Worked Example: Building Height
An observer stands $50\text{ m}$ away from the base of a tall building. The angle of elevation to the top of the building is $32^\circ$. If the observer's eye height is $1.6\text{ m}$, find the total height of the building.

**Step 1:** Model the right triangle:
- Adjacent side $A = 50\text{ m}$ (distance to building).
- Angle $\theta = 32^\circ$.
- Opposite side $O = h_{\text{above eyes}}$.

**Step 2:** Apply Tangent:
$$\tan(32^\circ) = \frac{O}{50} \implies O = 50 \cdot \tan(32^\circ) \approx 50 \cdot 0.6249 \approx 31.25\text{ m}$$

**Step 3:** Add eye level height:
$$\text{Total Height} = 31.25\text{ m} + 1.6\text{ m} = 32.85\text{ m}$$
MARKDOWN,
                    ]
                ],
                'assessment' => [
                    'title' => 'Week 7 Assessment: Elevation, Depression, Heights and Distances',
                    'description' => 'Test your ability to formulate and solve real-world word problems.',
                    'duration_minutes' => 30,
                    'questions' => [
                        [
                            'question_text' => 'From what baseline reference line are angles of elevation and depression always measured?',
                            'question_type' => 'multiple_choice',
                            'explanation' => 'They are always measured relative to the horizontal line of sight.',
                            'marks' => 1.00,
                            'options' => [
                                ['text' => 'The horizontal line of sight', 'is_correct' => true],
                                ['text' => 'The vertical cliff/building wall', 'is_correct' => false],
                                ['text' => 'The slant line of sight', 'is_correct' => false],
                                ['text' => 'Magnetic North', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'True or False: The angle of depression from a cliff top to a boat equals the angle of elevation from the boat to the cliff top.',
                            'question_type' => 'true_false',
                            'explanation' => 'True. Because horizontal lines are parallel, alternate interior angles are equal.',
                            'marks' => 1.00,
                            'options' => [
                                ['text' => 'True', 'is_correct' => true],
                                ['text' => 'False', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'A person stands 30 m from a tree. The angle of elevation to the treetop is 45°. If tan(45°) = 1, how tall is the tree in meters (ignoring eye height)?',
                            'question_type' => 'numerical',
                            'explanation' => 'Height = 30 * tan(45°) = 30 * 1 = 30 m.',
                            'marks' => 1.00,
                            'metadata' => ['correct_value' => 30, 'tolerance' => 0.1],
                            'options' => [
                                ['text' => '30', 'is_correct' => true],
                                ['text' => '15', 'is_correct' => false],
                                ['text' => '45', 'is_correct' => false],
                                ['text' => '60', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'A lighthouse is 60 m above sea level. From the top, the angle of depression to a buoy is 30°. If tan(30°) ≈ 0.577, what is the horizontal distance to the buoy to the nearest whole meter?',
                            'question_type' => 'numerical',
                            'explanation' => 'Distance = 60 / tan(30°) = 60 / 0.5774 ≈ 104 meters.',
                            'marks' => 1.00,
                            'metadata' => ['correct_value' => 104, 'tolerance' => 2],
                            'options' => [
                                ['text' => '104', 'is_correct' => true],
                                ['text' => '35', 'is_correct' => false],
                                ['text' => '120', 'is_correct' => false],
                                ['text' => '80', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'An airplane is at an altitude of 1000 m. If the straight-line distance (hypotenuse) to an airport runway is 2000 m, what is the angle of descent (depression) in degrees?',
                            'question_type' => 'numerical',
                            'explanation' => 'sin(θ) = 1000 / 2000 = 0.5. θ = sin^-1(0.5) = 30°.',
                            'marks' => 1.00,
                            'metadata' => ['correct_value' => 30, 'tolerance' => 0.1],
                            'options' => [
                                ['text' => '30', 'is_correct' => true],
                                ['text' => '45', 'is_correct' => false],
                                ['text' => '60', 'is_correct' => false],
                                ['text' => '15', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'A flagpole casts a shadow 12 m long when the angle of elevation of the sun is 60°. If tan(60°) ≈ 1.732, what is the flagpole height in meters (rounded to one decimal)?',
                            'question_type' => 'numerical',
                            'explanation' => 'Height = 12 * 1.732 ≈ 20.8 m.',
                            'marks' => 1.00,
                            'metadata' => ['correct_value' => 20.8, 'tolerance' => 0.5],
                            'options' => [
                                ['text' => '20.8', 'is_correct' => true],
                                ['text' => '15.4', 'is_correct' => false],
                                ['text' => '24.0', 'is_correct' => false],
                                ['text' => '18.2', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'True or False: If a word problem specifies an observer eye height of 1.7 m, that height must be added to the calculated triangle height to find the total object height.',
                            'question_type' => 'true_false',
                            'explanation' => 'True. The triangle is formed at eye level, so adding eye height gives the total height from the ground.',
                            'marks' => 1.00,
                            'options' => [
                                ['text' => 'True', 'is_correct' => true],
                                ['text' => 'False', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'If the angle of elevation to a helicopter is 90°, where is the helicopter located relative to the observer?',
                            'question_type' => 'multiple_choice',
                            'explanation' => 'An angle of 90° from horizontal points directly overhead (at the zenith).',
                            'marks' => 1.00,
                            'options' => [
                                ['text' => 'Directly overhead (zenith)', 'is_correct' => true],
                                ['text' => 'On the ground horizon', 'is_correct' => false],
                                ['text' => 'Behind the observer', 'is_correct' => false],
                                ['text' => 'At sea level', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'As you walk closer to the base of a skyscraper, what happens to the angle of elevation to its roof?',
                            'question_type' => 'multiple_choice',
                            'explanation' => 'As distance decreases, you must look steeper upwards, so the angle of elevation increases.',
                            'marks' => 1.00,
                            'options' => [
                                ['text' => 'It increases', 'is_correct' => true],
                                ['text' => 'It decreases', 'is_correct' => false],
                                ['text' => 'It stays constant', 'is_correct' => false],
                                ['text' => 'It drops to zero', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'What trigonometric ratio is most commonly used in height problems where the ground distance (adjacent) is known?',
                            'question_type' => 'multiple_choice',
                            'explanation' => 'Tangent is most common because ground distance is Adjacent and building height is Opposite: tan(θ) = O / A.',
                            'marks' => 1.00,
                            'options' => [
                                ['text' => 'Tangent', 'is_correct' => true],
                                ['text' => 'Sine', 'is_correct' => false],
                                ['text' => 'Cosine', 'is_correct' => false],
                                ['text' => 'Secant', 'is_correct' => false],
                            ]
                        ],
                    ]
                ]
            ],

            // ==========================================
            // WEEK 8: INTEGRATION AND CONSOLIDATION
            // ==========================================
            [
                'week_number' => 8,
                'title' => 'Integration, Consolidation, and Mastery',
                'slug' => 'week-8-integration-and-mastery',
                'description' => 'Synthesize all 7 preceding weeks: mixed multi-step trigonometry problems, remedial reviews, enrichment challenges, and final preparation.',
                'learning_objectives' => [
                    'Synthesize Pythagoras and trigonometric ratios in two-triangle compound diagrams.',
                    'Diagnose and correct frequent beginner misconceptions.',
                    'Explore enrichment concepts such as the Pythagorean Identity ($\sin^2\theta + \cos^2\theta = 1$).',
                    'Achieve comprehensive mastery ready for the final examination.',
                ],
                'lessons' => [
                    [
                        'title' => 'Multi-Step Problems and Compound Triangles',
                        'slug' => 'multi-step-problems-and-compound-triangles',
                        'summary' => 'Solving problems with shared sides, two interconnected triangles, and composite figures.',
                        'learning_objectives' => [
                            'Identify the shared "bridge side" between adjacent right triangles.',
                            'Solve compound figures sequentially.',
                        ],
                        'estimated_minutes' => 25,
                        'content' => <<<'MARKDOWN'
# Multi-Step Compound Triangle Problems

## 1. The "Bridge Side" Strategy
In complex diagrams containing two connected right triangles:
1. Locate the **shared side** (the "bridge").
2. Solve the triangle that contains **enough known values** to find this bridge side.
3. Transfer the calculated bridge length into the second triangle to find the final target variable.

### Worked Example:
Two right triangles share a common vertical altitude $h$:
- Triangle 1 has base angle $30^\circ$ and hypotenuse $20\text{ m}$.
  $$h = 20 \cdot \sin(30^\circ) = 20 \cdot 0.5 = 10\text{ m}$$
- Triangle 2 has altitude $h = 10\text{ m}$ and base angle $45^\circ$.
  Find its base $b$:
  $$\tan(45^\circ) = \frac{h}{b} \implies 1 = \frac{10}{b} \implies b = 10\text{ m}$$
MARKDOWN,
                    ],
                    [
                        'title' => 'Common Misconceptions, Remedial Reviews & Enrichment',
                        'slug' => 'common-misconceptions-remedial-reviews-and-enrichment',
                        'summary' => 'Review of core pitfalls and introduction to the fundamental Pythagorean Trigonometric Identity.',
                        'learning_objectives' => [
                            'Spot and fix common arithmetic and ratio errors.',
                            'Understand the Pythagorean Identity $\sin^2\theta + \cos^2\theta = 1$.',
                        ],
                        'estimated_minutes' => 25,
                        'content' => <<<'MARKDOWN'
# Misconceptions, Remedial Review & Enrichment

## 1. Top 4 Pitfalls to Avoid
1. **Wrong Calculator Mode**: Forgetting you are in RAD mode instead of DEG.
2. **Flipping Denominators**: Writing $x = 10 \cdot \sin(30^\circ)$ when $x$ was on the bottom ($x = \frac{10}{\sin(30^\circ)}$).
3. **Swapping Opposite and Adjacent**: Not labeling relative to the designated reference angle.
4. **Adding Squares for Legs**: Forgetting to subtract when finding a shorter leg with Pythagoras.

## 2. Enrichment: The Fundamental Pythagorean Identity
From Pythagoras ($a^2 + b^2 = c^2$), divide every term by $c^2$:
$$\left(\frac{a}{c}\right)^2 + \left(\frac{b}{c}\right)^2 = 1$$
Since $\frac{a}{c} = \cos\theta$ and $\frac{b}{c} = \sin\theta$:
$$\sin^2\theta + \cos^2\theta = 1$$
This famous identity holds true for ANY angle $\theta$!
MARKDOWN,
                    ]
                ],
                'assessment' => [
                    'title' => 'Week 8 Assessment: Mastery and Synthesis Assessment',
                    'description' => 'Comprehensive evaluation of multi-step problem solving and consolidated trigonometry understanding.',
                    'duration_minutes' => 30,
                    'questions' => [
                        [
                            'question_text' => 'What does sin^2(θ) + cos^2(θ) always equal for any real angle θ?',
                            'question_type' => 'numerical',
                            'explanation' => 'By the fundamental Pythagorean trigonometric identity, sin^2(θ) + cos^2(θ) = 1.',
                            'marks' => 1.00,
                            'metadata' => ['correct_value' => 1, 'tolerance' => 0],
                            'options' => [
                                ['text' => '1', 'is_correct' => true],
                                ['text' => '0', 'is_correct' => false],
                                ['text' => '2', 'is_correct' => false],
                                ['text' => '0.5', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'In a two-triangle compound problem, what is the best strategy?',
                            'question_type' => 'multiple_choice',
                            'explanation' => 'Find the shared bridge side first using the triangle with sufficient information.',
                            'marks' => 1.00,
                            'options' => [
                                ['text' => 'Solve for the shared bridge side first', 'is_correct' => true],
                                ['text' => 'Add all angles together', 'is_correct' => false],
                                ['text' => 'Multiply the two hypotenuses', 'is_correct' => false],
                                ['text' => 'Guess the unknown leg', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'If sin(θ) = 0.6, what is cos(θ) for an acute angle in a right triangle?',
                            'question_type' => 'multiple_choice',
                            'explanation' => 'cos(θ) = sqrt(1 - sin^2(θ)) = sqrt(1 - 0.36) = sqrt(0.64) = 0.8.',
                            'marks' => 1.00,
                            'options' => [
                                ['text' => '0.8', 'is_correct' => true],
                                ['text' => '0.4', 'is_correct' => false],
                                ['text' => '0.6', 'is_correct' => false],
                                ['text' => '0.5', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'True or False: If a right triangle has an angle of 45°, it is both a right triangle and an isosceles triangle.',
                            'question_type' => 'true_false',
                            'explanation' => 'True. The other acute angle is 90 - 45 = 45°, meaning two angles and two sides are equal.',
                            'marks' => 1.00,
                            'options' => [
                                ['text' => 'True', 'is_correct' => true],
                                ['text' => 'False', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'What is the value of tan(60°) * cos(60°) if sin(60°) = √3/2?',
                            'question_type' => 'multiple_choice',
                            'explanation' => 'tan(θ) * cos(θ) = (sin(θ)/cos(θ)) * cos(θ) = sin(θ) = √3/2.',
                            'marks' => 1.00,
                            'options' => [
                                ['text' => '√3/2', 'is_correct' => true],
                                ['text' => '1/2', 'is_correct' => false],
                                ['text' => '1', 'is_correct' => false],
                                ['text' => '√3', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'If the two acute angles of a right triangle are α and β, what does α + β equal in degrees?',
                            'question_type' => 'numerical',
                            'explanation' => '180 - 90 = 90 degrees.',
                            'marks' => 1.00,
                            'metadata' => ['correct_value' => 90, 'tolerance' => 0],
                            'options' => [
                                ['text' => '90', 'is_correct' => true],
                                ['text' => '180', 'is_correct' => false],
                                ['text' => '45', 'is_correct' => false],
                                ['text' => '60', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'True or False: sin(α) = cos(90° - α) for any acute angle α.',
                            'question_type' => 'true_false',
                            'explanation' => 'True! Co-functions of complementary angles are equal (the origin of the name "Co-sine").',
                            'marks' => 1.00,
                            'options' => [
                                ['text' => 'True', 'is_correct' => true],
                                ['text' => 'False', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'In a triangle with sides 5, 12, 13, what is sin(θ) for the angle opposite side 12?',
                            'question_type' => 'multiple_choice',
                            'explanation' => 'sin(θ) = Opposite / Hypotenuse = 12 / 13.',
                            'marks' => 1.00,
                            'options' => [
                                ['text' => '12/13', 'is_correct' => true],
                                ['text' => '5/13', 'is_correct' => false],
                                ['text' => '12/5', 'is_correct' => false],
                                ['text' => '5/12', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'Can you use trigonometry to find the height of a mountain without physically climbing to its summit?',
                            'question_type' => 'true_false',
                            'explanation' => 'True! By measuring distance and angles of elevation with surveying instruments, trigonometry accurately calculates inaccessible heights.',
                            'marks' => 1.00,
                            'options' => [
                                ['text' => 'True', 'is_correct' => true],
                                ['text' => 'False', 'is_correct' => false],
                            ]
                        ],
                        [
                            'question_text' => 'What is the minimum score required in this system to pass each weekly assessment and unlock the next topic?',
                            'question_type' => 'numerical',
                            'explanation' => 'The system enforces a 70% passing threshold.',
                            'marks' => 1.00,
                            'metadata' => ['correct_value' => 70, 'tolerance' => 0],
                            'options' => [
                                ['text' => '70', 'is_correct' => true],
                                ['text' => '50', 'is_correct' => false],
                                ['text' => '60', 'is_correct' => false],
                                ['text' => '80', 'is_correct' => false],
                            ]
                        ],
                    ]
                ]
            ],
        ];
    }
}
