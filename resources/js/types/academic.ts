export type Semester = 'ganjil' | 'genap';

export type GradeLevel = '10' | '11' | '12';

export type AcademicYear = {
    id: number;
    name: string;
    semester: Semester;
    is_active: boolean;
    starts_at: string;
    ends_at: string;
    created_at: string;
    updated_at: string;
    classrooms_count?: number;
};

export type Department = {
    id: number;
    name: string;
    code: string;
    created_at: string;
    updated_at: string;
    classrooms_count?: number;
    subjects_count?: number;
};

export type Classroom = {
    id: number;
    name: string;
    academic_year_id: number;
    department_id: number;
    grade_level: GradeLevel;
    created_at: string;
    updated_at: string;
    academic_year?: AcademicYear;
    department?: Department;
    students_count?: number;
    students?: import('./auth').User[];
};

export type Subject = {
    id: number;
    name: string;
    code: string;
    department_id: number | null;
    created_at: string;
    updated_at: string;
    department?: Department;
};

export type ClassroomSubjectTeacher = {
    id: number;
    classroom_id: number;
    subject_id: number;
    user_id: number;
    classroom?: Classroom;
    subject?: Subject;
    user?: import('./auth').User;
};

export type PaginatedData<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};
