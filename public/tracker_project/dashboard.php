<!DOCTYPE html>
<html>
<head>
    <title>Vehicle Tracking - Ridex</title>
    <style>
        body {
            font-family: Arial, sans-serif;
        }
        #map {
            width: 90%;
            height: 500px;
            margin: 20px auto;
            border: 2px solid #ccc;
        }
        #info {
            text-align: center;
            margin: 10px;
            font-size: 14px;
        }
        #openMapsBtn {
            padding: 8px 16px;
            background: #1a73e8;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin: 5px;
        }
        #playBtn {
            padding: 8px 16px;
            background: green;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin: 5px;
        }
        #alertBox {
            color: red;
            font-weight: bold;
            text-align: center;
            display: none;
            padding: 10px;
        }
    </style>
</head>
<body>

<h2 style="text-align:center;">Live Vehicle Tracking - Ridex</h2>

<p id="alertBox">WARNING: Vehicle is outside safe area!</p>

<div style="text-align:center;">
    <button id="playBtn" onclick="startPlayback()">Show Route Playback</button>
    <button id="openMapsBtn" onclick="openGoogleMaps()">Open in Google Maps</button>
</div>

<div id="map"></div>

<div id="info">
    Last updated: <span id="lastUpdate">loading...</span> |
    Position: <span id="posText">loading...</span>
</div>

<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBSGTC4KHr_F1MI92xDvs7LW6rJo282tEE"></script>

<script>

var map;
var marker;
var currentLat = 27.6942;
var currentLng = 85.3423;

// coordinates around Sinamangal for route playback
var routePoints = [
    {lat: 27.6942, lng: 85.3423},
    {lat: 27.6950, lng: 85.3440},
    {lat: 27.6965, lng: 85.3460},
    {lat: 27.6980, lng: 85.3480},
    {lat: 27.6995, lng: 85.3500},
    {lat: 27.6980, lng: 85.3520},
    {lat: 27.6960, lng: 85.3510},
    {lat: 27.6942, lng: 85.3423}
];

function initMap() {

    map = new google.maps.Map(document.getElementById("map"), {
        zoom: 15,
        center: {lat: currentLat, lng: currentLng}
    });

    marker = new google.maps.Marker({
        position: {lat: currentLat, lng: currentLng},
        map: map,
        title: "Vehicle"
    });

    // click marker to see navigation link
    marker.addListener("click", function() {
        var info = new google.maps.InfoWindow({
            content: '<p>Vehicle is here</p><a href="https://www.google.com/maps?q=' + currentLat + ',' + currentLng + '" target="_blank">Navigate here</a>'
        });
        info.open(map, marker);
    });

    loadLocation();

    // auto refresh every 15 seconds
    setInterval(loadLocation, 15000);
}

function loadLocation() {

    fetch("get.php")
    .then(function(res) { return res.json(); })
    .then(function(data) {
        if(data.lat && data.lng) {
            currentLat = parseFloat(data.lat);
            currentLng = parseFloat(data.lng);
            marker.setPosition({lat: currentLat, lng: currentLng});
            map.setCenter({lat: currentLat, lng: currentLng});
            checkSafeZone(currentLat, currentLng);
        }
    })
    .catch(function() {
        console.log("could not get location");
    });

    var now = new Date();
    document.getElementById("lastUpdate").innerText = now.toLocaleTimeString();
    document.getElementById("posText").innerText = currentLat.toFixed(4) + ", " + currentLng.toFixed(4);
}

// checks if vehicle goes outside kathmandu area
function checkSafeZone(lat, lng) {
    if(lat < 27.60 || lat > 27.80 || lng < 85.20 || lng > 85.45) {
        document.getElementById("alertBox").style.display = "block";
    } else {
        document.getElementById("alertBox").style.display = "none";
    }
}

var playIndex = 0;
var playTimer = null;

function startPlayback() {
    if(playTimer != null) {
        clearInterval(playTimer);
        playTimer = null;
        document.getElementById("playBtn").innerText = "Show Route Playback";
        return;
    }

    playIndex = 0;
    document.getElementById("playBtn").innerText = "Stop Playback";

    playTimer = setInterval(function() {
        if(playIndex >= routePoints.length) {
            playIndex = 0;
        }
        var point = routePoints[playIndex];
        marker.setPosition(point);
        map.panTo(point);
        document.getElementById("posText").innerText = point.lat.toFixed(4) + ", " + point.lng.toFixed(4);
        playIndex++;
    }, 2000);
}

function openGoogleMaps() {
    window.open("https://www.google.com/maps?q=" + currentLat + "," + currentLng, "_blank");
}

window.onload = initMap;

</script>

</body>
</html>