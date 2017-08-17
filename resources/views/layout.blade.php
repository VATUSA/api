<!DOCTYPE html>
<html lang='en'>
  <head>
    <title>VATUSA - @yield('title')</title>
    <link href='/semantic/semantic.min.css' rel='stylesheet' type='text/css'>
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta charset="utf-8">
  </head>
  <body>
    <!-- Header -->
    <section class="blueHeader">
      <div class="ui container">
        <div class="ui stackable grid">
          <div class="twelve wide column">
            <img src="/images/logo-dark.png" class="ui image" style="margin-right: 1.5em">
          </div>
          <div class="four wide column middle aligned">
            <div class="ui red card">
              <div class="content">
                <div><h3 class="ui red header">Daniel Hawton (876594)</h3></div>
                <div>Controller (C1)</div>
                <div class="ui blue text">HCF - Honolulu Control Facility</div>
              </div>
            </div>
            </div>
        </div>
      </div>
      <div class="ui large inverted stackable menu" style="margin-bottom: 1.5em">
        <div class="ui container">
          <a class="item" href="/">Home</a>
          <div class="ui dropdown item">
            Facilities <i class="dropdown icon"></i>
            <div class="menu">
              @foreach(\FacilityHelper::getFacilities("name", false) as $facility)
                <a class="{{$facility->url}}" target="_blank">{{$facility->id}} - {{$facility->name}}</a>
              @endforeach
            </div>
          </div>
        </div>
      </div>
    </section>
    <!-- Footer -->
    <script src="/js/app.js" type="text/javascript"></script>
    <script src="/semantic/semantic.min.js" type="text/javascript"></script>
  </body>
</html>
