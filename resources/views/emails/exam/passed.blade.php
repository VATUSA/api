@extends('emails.layout')
@section('title','Exam Passed')
@section('content')
    Dear {{ $data['student_name'] }},
    <br><br>
    This email is to notify you that you <strong>passed</strong> your assigned exam!
    <br><br>
    Exam: {{ $data['exam_name'] }}<br>
    Score: {{ $data['correct'] }}/{{ $data['possible'] }} ({{$data['score']}}%)
    <br><br>
    A copy of this has also been sent to your training staff.
    <br><br>
@endsection