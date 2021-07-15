importScripts('https://www.gstatic.com/firebasejs/8.7.1/firebase-app.js');
importScripts('https://www.gstatic.com/firebasejs/8.7.1/firebase-messaging.js');

firebase.initializeApp({
    apiKey: "AIzaSyArpJAngzyhm_XHmYRc-r1dRzauQfL1y50",
    authDomain: "follow-gt.firebaseapp.com",
    projectId: "follow-gt",
    storageBucket: "follow-gt.appspot.com",
    messagingSenderId: "217220633913",
    appId: "1:217220633913:web:ec324be7663f1ba1e704e8",
    measurementId: "G-YR5FK3KFQT"
});

// Retrieve an instance of Firebase Messaging so that it can handle background
// messages.
const messaging = firebase.messaging();
