<?php

return [

    'public' => [
        '/api/v1/health',
        '/api/v1/semesters/count-active',
        '/api/v1/auth/callback',
        '/api/v1/auth/refresh',
        '/api/v1/auth/logout',
    ],

    'catalog' => [
        // 5.1 Appointments (10)
        ['method' => 'GET',    'path' => '/api/v1/appointments',                              'required_level' => 'read'],
        ['method' => 'POST',   'path' => '/api/v1/appointments',                              'required_level' => 'write'],
        ['method' => 'GET',    'path' => '/api/v1/appointments/faculty-booked',               'required_level' => 'read'],
        ['method' => 'POST',   'path' => '/api/v1/appointments/batch',                        'required_level' => 'write'],
        ['method' => 'GET',    'path' => '/api/v1/appointments/{id}',                         'required_level' => 'read'],
        ['method' => 'POST',   'path' => '/api/v1/appointments/{id}/{action}',                'required_level' => 'write'],
        ['method' => 'POST',   'path' => '/api/v1/appointments/{id}/files',                   'required_level' => 'write'],
        ['method' => 'POST',   'path' => '/api/v1/appointments/{id}/retry-sync',              'required_level' => 'write'],
        ['method' => 'POST',   'path' => '/api/v1/appointments/{id}/student-cancel',          'required_level' => 'write'],
        ['method' => 'POST',   'path' => '/api/v1/appointments/slots/{slotId}/teams-link',    'required_level' => 'write'],

        // 5.2 Availability (2)
        ['method' => 'GET',    'path' => '/api/v1/availability-rules',                        'required_level' => 'read'],
        ['method' => 'POST',   'path' => '/api/v1/availability-rules',                        'required_level' => 'write'],

        // 5.3 Admin users (9)
        ['method' => 'GET',    'path' => '/api/v1/admin/users',                               'required_level' => 'read'],
        ['method' => 'POST',   'path' => '/api/v1/admin/users',                               'required_level' => 'admin'],
        ['method' => 'PATCH',  'path' => '/api/v1/admin/users',                               'required_level' => 'write'],
        ['method' => 'GET',    'path' => '/api/v1/admin/users/deleted',                       'required_level' => 'read'],
        ['method' => 'POST',   'path' => '/api/v1/admin/users/bulk-soft-delete',              'required_level' => 'admin'],
        ['method' => 'GET',    'path' => '/api/v1/users/{id}/related-data',             'required_level' => 'read'],
        ['method' => 'DELETE', 'path' => '/api/v1/admin/users/{id}',                          'required_level' => 'admin'],
        ['method' => 'POST',   'path' => '/api/v1/admin/users/{id}/soft-delete',              'required_level' => 'admin'],
        ['method' => 'POST',   'path' => '/api/v1/admin/users/{id}/restore',                  'required_level' => 'admin'],

        // 5.4 Academic (15)
        ['method' => 'GET',    'path' => '/api/v1/departments',                         'required_level' => 'read'],
        ['method' => 'POST',   'path' => '/api/v1/departments',                         'required_level' => 'admin'],
        ['method' => 'PATCH',  'path' => '/api/v1/departments/{id}',                    'required_level' => 'admin'],
        ['method' => 'GET',    'path' => '/api/v1/department-courses',                  'required_level' => 'read'],
        ['method' => 'POST',   'path' => '/api/v1/department-courses',                  'required_level' => 'write'],
        ['method' => 'DELETE', 'path' => '/api/v1/department-courses/{id}',             'required_level' => 'admin'],
        ['method' => 'POST',   'path' => '/api/v1/subjects',                            'required_level' => 'admin'],
        ['method' => 'PATCH',  'path' => '/api/v1/subjects/{id}',                       'required_level' => 'admin'],
        ['method' => 'POST',   'path' => '/api/v1/sections',                            'required_level' => 'admin'],
        ['method' => 'POST',   'path' => '/api/v1/sections/fix-names',                  'required_level' => 'admin'],
        ['method' => 'PATCH',  'path' => '/api/v1/sections/{id}',                       'required_level' => 'admin'],
        ['method' => 'POST',   'path' => '/api/v1/faculty-subjects',                    'required_level' => 'admin'],
        ['method' => 'POST',   'path' => '/api/v1/faculty-subjects/reassign',           'required_level' => 'admin'],
        ['method' => 'POST',   'path' => '/api/v1/student-enrollments',                 'required_level' => 'admin'],
        ['method' => 'DELETE', 'path' => '/api/v1/student-enrollments/{id}',            'required_level' => 'admin'],

        // 5.5 Semesters gated (7; count-active is public)
        ['method' => 'GET',    'path' => '/api/v1/semesters',                                 'required_level' => 'read'],
        ['method' => 'POST',   'path' => '/api/v1/semesters',                                 'required_level' => 'write'],
        ['method' => 'GET',    'path' => '/api/v1/semesters/{id}',                            'required_level' => 'read'],
        ['method' => 'POST',   'path' => '/api/v1/semesters/{id}',                            'required_level' => 'admin'],
        ['method' => 'PATCH',  'path' => '/api/v1/semesters/{id}',                            'required_level' => 'admin'],
        ['method' => 'DELETE', 'path' => '/api/v1/semesters/{id}',                            'required_level' => 'admin'],
        ['method' => 'GET',    'path' => '/api/v1/semesters/{id}/impacts',                    'required_level' => 'admin'],

        // 5.6 Evaluations (12)
        ['method' => 'GET',    'path' => '/api/v1/evaluations',                               'required_level' => 'read'],
        ['method' => 'POST',   'path' => '/api/v1/evaluations',                               'required_level' => 'write'],
        ['method' => 'GET',    'path' => '/api/v1/evaluations/pending',                       'required_level' => 'read'],
        ['method' => 'POST',   'path' => '/api/v1/evaluations/dispute',                       'required_level' => 'write'],
        ['method' => 'GET',    'path' => '/api/v1/evaluations/{id}',                          'required_level' => 'read'],
        ['method' => 'GET',    'path' => '/api/v1/evaluations/{id}/ratings',                  'required_level' => 'read'],
        ['method' => 'PUT',    'path' => '/api/v1/evaluations/{id}/ratings',                  'required_level' => 'write'],
        ['method' => 'GET',    'path' => '/api/v1/evaluations/{id}/comments',                 'required_level' => 'read'],
        ['method' => 'POST',   'path' => '/api/v1/evaluations/{id}/comments',                 'required_level' => 'write'],
        ['method' => 'POST',   'path' => '/api/v1/evaluations/{id}/submit',                   'required_level' => 'write'],
        ['method' => 'GET',    'path' => '/api/v1/evaluation-comments',                       'required_level' => 'read'],
        ['method' => 'GET',    'path' => '/api/v1/evaluations/bootstrap',             'required_level' => 'write'],

        // 5.7 Evaluation periods (12)
        ['method' => 'GET',    'path' => '/api/v1/evaluation-periods',                        'required_level' => 'read'],
        ['method' => 'POST',   'path' => '/api/v1/evaluation-periods',                        'required_level' => 'admin'],
        ['method' => 'GET',    'path' => '/api/v1/evaluation-periods/{id}',                   'required_level' => 'read'],
        ['method' => 'PUT',    'path' => '/api/v1/evaluation-periods/{id}',                   'required_level' => 'admin'],
        ['method' => 'DELETE', 'path' => '/api/v1/evaluation-periods/{id}',                   'required_level' => 'admin'],
        ['method' => 'POST',   'path' => '/api/v1/evaluation-periods/{id}/activate',          'required_level' => 'admin'],
        ['method' => 'POST',   'path' => '/api/v1/evaluation-periods/{id}/reset',             'required_level' => 'admin'],
        ['method' => 'GET',    'path' => '/api/v1/evaluation-periods/{id}/rubric',            'required_level' => 'read'],
        ['method' => 'POST',   'path' => '/api/v1/evaluation-periods/{id}/rubric/copy',       'required_level' => 'read'],
        ['method' => 'POST',   'path' => '/api/v1/evaluation-periods/{id}/rubrics/items',     'required_level' => 'admin'],
        ['method' => 'PATCH',  'path' => '/api/v1/evaluation-periods/{id}/rubrics/items/{itemId}',  'required_level' => 'admin'],
        ['method' => 'DELETE', 'path' => '/api/v1/evaluation-periods/{id}/rubrics/items/{itemId}',  'required_level' => 'admin'],

        // 5.8 Evaluation results (15, single scoped surface)
        ['method' => 'GET',    'path' => '/api/v1/evaluation-results',                  'required_level' => 'read'],
        ['method' => 'GET',    'path' => '/api/v1/evaluation-results/department',        'required_level' => 'read'],
        ['method' => 'GET',    'path' => '/api/v1/evaluation-results/details',           'required_level' => 'read'],
        ['method' => 'GET',    'path' => '/api/v1/evaluation-results/subjects',          'required_level' => 'read'],
        ['method' => 'GET',    'path' => '/api/v1/evaluation-results/subjects/{facultySubjectId}', 'required_level' => 'read'],
        ['method' => 'GET',    'path' => '/api/v1/evaluation-results/departments/{departmentId}', 'required_level' => 'read'],
        ['method' => 'GET',    'path' => '/api/v1/evaluation-results/faculty/{facultyId}', 'required_level' => 'read'],
        ['method' => 'GET',    'path' => '/api/v1/evaluation-results/groups/{facultySubjectId}', 'required_level' => 'read'],
        ['method' => 'POST',   'path' => '/api/v1/evaluation-results/invalidate',       'required_level' => 'admin'],
        ['method' => 'POST',   'path' => '/api/v1/evaluation-results/visibility',       'required_level' => 'admin'],
        ['method' => 'GET',    'path' => '/api/v1/evaluations/disabled',                'required_level' => 'read'],
        ['method' => 'DELETE', 'path' => '/api/v1/evaluations/disabled',                'required_level' => 'admin'],
        ['method' => 'POST',   'path' => '/api/v1/evaluations/disabled/restore',        'required_level' => 'admin'],
        ['method' => 'GET',    'path' => '/api/v1/evaluations/{evaluationId}/details',  'required_level' => 'read'],
        ['method' => 'POST',   'path' => '/api/v1/evaluations/{evaluationId}/invalidate', 'required_level' => 'admin'],

        // 5.9 Rubric groups (12)
        ['method' => 'GET',    'path' => '/api/v1/rubric-groups',                             'required_level' => 'read'],
        ['method' => 'POST',   'path' => '/api/v1/rubric-groups',                             'required_level' => 'admin'],
        ['method' => 'GET',    'path' => '/api/v1/rubric-groups/{id}',                        'required_level' => 'read'],
        ['method' => 'PATCH',  'path' => '/api/v1/rubric-groups/{id}',                        'required_level' => 'admin'],
        ['method' => 'DELETE', 'path' => '/api/v1/rubric-groups/{id}',                        'required_level' => 'admin'],
        ['method' => 'POST',   'path' => '/api/v1/rubric-groups/{id}/items',                  'required_level' => 'admin'],
        ['method' => 'PATCH',  'path' => '/api/v1/rubric-groups/{id}/items/{itemId}',         'required_level' => 'admin'],
        ['method' => 'DELETE', 'path' => '/api/v1/rubric-groups/{id}/items/{itemId}',         'required_level' => 'admin'],
        ['method' => 'POST',   'path' => '/api/v1/rubric-groups/{id}/duplicate',              'required_level' => 'admin'],
        ['method' => 'GET',    'path' => '/api/v1/rubric-groups/{id}/snapshot',               'required_level' => 'read'],
        ['method' => 'POST',   'path' => '/api/v1/rubric-groups/{id}/categories',             'required_level' => 'admin'],
        ['method' => 'DELETE', 'path' => '/api/v1/rubric-groups/{id}/categories',             'required_level' => 'admin'],

        // 5.10 Import (11)
        ['method' => 'POST',   'path' => '/api/v1/import/preview',                            'required_level' => 'admin'],
        ['method' => 'GET',    'path' => '/api/v1/import/users/reference',                    'required_level' => 'read'],
        ['method' => 'GET',    'path' => '/api/v1/import/departments-courses/reference',      'required_level' => 'read'],
        ['method' => 'POST',   'path' => '/api/v1/import/departments-courses',                'required_level' => 'admin'],
        ['method' => 'GET',    'path' => '/api/v1/import/faculties/reference',                'required_level' => 'read'],
        ['method' => 'POST',   'path' => '/api/v1/import/faculties',                          'required_level' => 'admin'],
        ['method' => 'GET',    'path' => '/api/v1/import/students/reference',                 'required_level' => 'read'],
        ['method' => 'GET',    'path' => '/api/v1/import/students',                           'required_level' => 'read'],
        ['method' => 'POST',   'path' => '/api/v1/import/students',                           'required_level' => 'admin'],
        ['method' => 'GET',    'path' => '/api/v1/import/subjects/reference',                 'required_level' => 'read'],
        ['method' => 'GET',    'path' => '/api/v1/import/sections/reference',                 'required_level' => 'read'],

        // 5.11 Data & audit (6)
        ['method' => 'GET',    'path' => '/api/v1/admin/audit-logs',                          'required_level' => 'read'],
        ['method' => 'DELETE', 'path' => '/api/v1/admin/audit-logs',                          'required_level' => 'admin'],
        ['method' => 'POST',   'path' => '/api/v1/data/delete-students',                'required_level' => 'admin'],
        ['method' => 'POST',   'path' => '/api/v1/data/export-consultations',           'required_level' => 'admin'],
        ['method' => 'POST',   'path' => '/api/v1/data/reset-db',                       'required_level' => 'admin'],
        ['method' => 'GET',    'path' => '/api/v1/data/evaluation-mappings',                  'required_level' => 'read'],

        // 5.12 User lookup (2)
        ['method' => 'GET',    'path' => '/api/v1/users/primary',                             'required_level' => 'read'],
        ['method' => 'GET',    'path' => '/api/v1/users/attendees',                           'required_level' => 'read'],
    ],

];
