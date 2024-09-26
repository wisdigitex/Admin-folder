importScripts('https://www.gstatic.com/firebasejs/8.3.2/firebase-app.js');
importScripts('https://www.gstatic.com/firebasejs/8.3.2/firebase-messaging.js');

firebase.initializeApp({
    apiKey: "AIzaSyB-74LEOSvZAl9pgtOvLgY0bOYwlHIBmSE",
    authDomain: "eniza-hypermarket.firebaseapp.com",
    projectId: "eniza-hypermarket",
    storageBucket: "eniza-hypermarket.appspot.com",
    messagingSenderId: "987650730174",
    appId: "1:987650730174:web:92a2dcb9e1e880a26996ef",
    measurementId: "G-PD78193EJT"
});

const messaging = firebase.messaging();
messaging.setBackgroundMessageHandler(function (payload) {
    return self.registration.showNotification(payload.data.title, {
        body: payload.data.body ? payload.data.body : '',
        icon: payload.data.icon ? payload.data.icon : ''
    });
});