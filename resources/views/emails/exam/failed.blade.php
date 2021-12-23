@extends('emails.layout')
@section('title','Exam Failed')
@section('content')
    Dear {{ $data['student_name'] }},
    <br><br>
    This email is to notify you that you <strong>did not pass</strong> your assigned exam.
    <br><br>
    Exam: {{ $data['exam_name'] }}<br>
    Score: {{ $data['correct'] }}/{{ $data['possible'] }} ({{$data['score']}}%)
    <br><br>
    @if($data['reassign'] > 0)
        Your exam will be reassigned in {{$data['reassign']}} day(s).
    @else
        Your exam will be reassigned by your training staff.
    @endif
    <br><br>
    A copy of this has also been sent to your training staff.
    <br><br>
@endsection