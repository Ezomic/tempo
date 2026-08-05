"""The step-tree builder is the piece most likely to break on a garminconnect
bump: it depends on that package's workout API and on Garmin's flat stepOrder
contract, neither of which is checked anywhere else."""

from __future__ import annotations

import itertools

import pytest
from fastapi import HTTPException

import main
from main import WorkoutStepBody, build_workout_steps


def step(**kwargs) -> WorkoutStepBody:
    return WorkoutStepBody(**kwargs)


def test_orders_steps_from_one():
    built = build_workout_steps(
        [
            step(type="warmup", seconds=600),
            step(type="interval", seconds=300),
            step(type="cooldown", seconds=300),
        ],
        itertools.count(1),
    )

    assert [s.stepOrder for s in built] == [1, 2, 3]


def test_repeat_group_takes_an_order_before_its_children():
    # Garmin expects one flat sequence across the whole tree, with the group
    # itself numbered ahead of the steps it contains.
    built = build_workout_steps(
        [
            step(type="warmup", seconds=600),
            step(
                type="repeat",
                iterations=4,
                steps=[
                    step(type="interval", seconds=60),
                    step(type="recovery", seconds=120),
                ],
            ),
            step(type="cooldown", seconds=300),
        ],
        itertools.count(1),
    )

    warm, group, cool = built

    assert warm.stepOrder == 1
    assert group.stepOrder == 2
    assert [s.stepOrder for s in group.workoutSteps] == [3, 4]
    assert cool.stepOrder == 5
    assert group.numberOfIterations == 4


def test_nested_repeats_keep_one_continuous_sequence():
    built = build_workout_steps(
        [
            step(
                type="repeat",
                iterations=2,
                steps=[
                    step(type="interval", seconds=60),
                    step(
                        type="repeat",
                        iterations=3,
                        steps=[step(type="recovery", seconds=30)],
                    ),
                ],
            )
        ],
        itertools.count(1),
    )

    outer = built[0]
    inner = outer.workoutSteps[1]

    assert outer.stepOrder == 1
    assert outer.workoutSteps[0].stepOrder == 2
    assert inner.stepOrder == 3
    assert inner.workoutSteps[0].stepOrder == 4


def test_carries_the_description_through():
    built = build_workout_steps(
        [step(type="interval", seconds=60, description="4x1min hard")],
        itertools.count(1),
    )

    assert built[0].description == "4x1min hard"


def test_pace_target_is_sent_as_speed_bounds():
    built = build_workout_steps(
        [step(type="interval", seconds=60, target_pace_low=2.5, target_pace_high=3.1)],
        itertools.count(1),
    )

    target = built[0].targetType

    assert target["workoutTargetTypeKey"] == "pace.zone"
    assert built[0].targetValueOne == 2.5
    assert built[0].targetValueTwo == 3.1


def test_heart_rate_zone_target():
    built = build_workout_steps(
        [step(type="interval", seconds=600, target_hr_zone=2)],
        itertools.count(1),
    )

    assert built[0].targetType["workoutTargetTypeKey"] == "heart.rate.zone"
    assert built[0].zoneNumber == 2


def test_pace_wins_when_both_targets_are_given():
    built = build_workout_steps(
        [
            step(
                type="interval",
                seconds=60,
                target_pace_low=2.5,
                target_pace_high=3.1,
                target_hr_zone=4,
            )
        ],
        itertools.count(1),
    )

    assert built[0].targetType["workoutTargetTypeKey"] == "pace.zone"


def test_a_half_specified_pace_target_is_ignored():
    # Garmin needs both bounds, so one on its own is dropped and the step keeps
    # the factory default rather than going out as a malformed target.
    built = build_workout_steps(
        [step(type="interval", seconds=60, target_pace_low=2.5)],
        itertools.count(1),
    )

    assert built[0].targetType["workoutTargetTypeKey"] == "no.target"
    assert getattr(built[0], "targetValueOne", None) is None


def test_unknown_step_type_is_rejected():
    with pytest.raises(HTTPException) as raised:
        build_workout_steps([step(type="sprint", seconds=60)], itertools.count(1))

    assert raised.value.status_code == 422


def test_every_advertised_step_type_builds():
    types = list(main._STEP_FACTORIES)

    built = build_workout_steps(
        [step(type=t, seconds=60) for t in types], itertools.count(1)
    )

    assert len(built) == len(types)
