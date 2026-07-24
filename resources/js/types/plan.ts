export interface IntensityOption {
    value: string;
    label: string;
    zone: number;
    hr_percent: string;
    feel: string;
    color: string;
}

export interface WorkoutTypeOption {
    value: string;
    label: string;
}

export interface WorkoutStep {
    position: number;
    repeat: number;
    duration_min: number;
    recovery_min: number | null;
    intensity: IntensityOption;
    recovery_intensity: IntensityOption | null;
    label: string | null;
}

export interface PlannedWorkout {
    id: number;
    date: string;
    sport: string;
    workout_type: WorkoutTypeOption | null;
    title: string;
    notes: string | null;
    duration_min: number | null;
    description: string;
    steps: WorkoutStep[];
    pushed: boolean;
    chronos_url: string | null;
}

export interface FormStep {
    repeat: number | string;
    duration_min: number | string;
    intensity: string;
    recovery_min: number | string;
    recovery_intensity: string;
    label: string;
}
