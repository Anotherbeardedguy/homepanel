@extends('layouts.admin')
@section('title', $chore->exists ? 'Muokkaa kotityötä' : 'Uusi kotityö')

@section('content')
    <h1>{{ $chore->exists ? 'Muokkaa kotityötä' : 'Uusi kotityö' }}</h1>
    <form method="post" action="{{ $chore->exists ? route('admin.chores.update', $chore) : route('admin.chores.store') }}" class="stack-form">
        @csrf
        @if ($chore->exists)
            @method('PUT')
        @endif
        <label for="title">Otsikko</label>
        <input id="title" name="title" value="{{ old('title', $chore->title) }}" required maxlength="120">

        <label for="description">Kuvaus</label>
        <textarea id="description" name="description" maxlength="1000">{{ old('description', $chore->description) }}</textarea>

        <fieldset>
            <legend>Tekijät</legend>
            <p class="muted">Ei valintaa: kuka ehtii. Yksi nimi: sama tekijä. Useampi nimi: vuoro nimien järjestyksessä, kunnes työ arkistoidaan.</p>
            @php($chosen = array_map('strval', old('member_ids', $selectedMembers)))
            @foreach ($members as $member)
                <label class="check">
                    <input type="checkbox" name="member_ids[]" value="{{ $member->id }}" @checked(in_array((string) $member->id, $chosen, true))>
                    {{ $member->display_name }}
                </label>
            @endforeach
        </fieldset>

        <label for="reward_eur">Korvaus, euroa</label>
        <input id="reward_eur" name="reward_eur" inputmode="decimal" value="{{ old('reward_eur', $chore->reward_eur) }}" placeholder="1,50">

        <label for="recurrence">Toistuminen</label>
        @php($recurrence = old('recurrence', $chore->recurrence?->value ?? 'once'))
        <select id="recurrence" name="recurrence">
            @foreach (\App\Enums\ChoreRecurrence::cases() as $case)
                <option value="{{ $case->value }}" @selected($recurrence === $case->value)>{{ $case->label() }}</option>
            @endforeach
        </select>

        <fieldset>
            <legend>Viikonpäivät</legend>
            @php($selectedDays = array_map('strval', old('weekdays', $chore->weekdays ?? [])))
            @foreach ([1 => 'Ma', 2 => 'Ti', 3 => 'Ke', 4 => 'To', 5 => 'Pe', 6 => 'La', 7 => 'Su'] as $number => $label)
                <label class="check">
                    <input type="checkbox" name="weekdays[]" value="{{ $number }}" @checked(in_array((string) $number, $selectedDays, true))>
                    {{ $label }}
                </label>
            @endforeach
        </fieldset>

        <label for="interval_days">Väli, päiviä</label>
        @php($interval = (string) old('interval_days', $chore->interval_days))
        <select id="interval_days" name="interval_days">
            <option value="">Ei väliä</option>
            @foreach ([2, 3, 7] as $days)
                <option value="{{ $days }}" @selected($interval === (string) $days)>Joka {{ $days }}. päivä</option>
            @endforeach
        </select>

        <label for="due_on">Eräpäivä</label>
        <input id="due_on" name="due_on" type="date" value="{{ old('due_on', $chore->due_on?->toDateString()) }}">

        <label for="due_time">Kellonaika</label>
        <input id="due_time" name="due_time" type="time" value="{{ old('due_time', $chore->due_time ? substr((string) $chore->due_time, 0, 5) : '') }}">

        <label class="check">
            <input type="checkbox" name="active" value="1" @checked(old('active', $chore->active ?? true))>
            Aktiivinen
        </label>

        <button type="submit">Tallenna</button>
    </form>
@endsection
