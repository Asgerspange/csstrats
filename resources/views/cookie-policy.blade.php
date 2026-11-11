@extends('layouts.page')

@section('title', 'Cookie Policy | CSSstrats.dk')

@section('content')
<div class="cookie-policy-container">
    <div class="cookie-policy-content">
        <h1 class="cookie-policy-title">Cookie Policy of csstrats.dk</h1>
        
        <div class="cookie-policy-text">
            <p>
                This document informs Users about the technologies that help csstrats.dk to achieve the purposes described below. 
                Such technologies allow the Owner to access and store information (for example by using a Cookie) or use resources 
                (for example by running a script) on a User's device as they interact with csstrats.dk.
            </p>
            
            <p>
                For simplicity, all such technologies are defined as "Trackers" within this document – unless there is a reason to differentiate.
                For example, while Cookies can be used on both web and mobile browsers, it would be inaccurate to talk about Cookies in the 
                context of mobile apps as they are a browser-based Tracker. For this reason, within this document, the term Cookies is only 
                used where it is specifically meant to indicate that particular type of Tracker.
            </p>
            
            <p>
                Some of the purposes for which Trackers are used may also require the User's consent. Whenever consent is given, 
                it can be freely withdrawn at any time following the instructions provided in this document.
            </p>
            
            <p>
                Csstrats.dk uses Trackers managed directly by the Owner (so-called "first-party" Trackers) and Trackers that enable 
                services provided by a third-party (so-called "third-party" Trackers). Unless otherwise specified within this document, 
                third-party providers may access the Trackers managed by them.
            </p>
            
            <p>
                The validity and expiration periods of Cookies and other similar Trackers may vary depending on the lifetime set by the 
                Owner or the relevant provider. Some of them expire upon termination of the User's browsing session.
                In addition to what's specified in the descriptions within each of the categories below, Users may find more precise and 
                updated information regarding lifetime specification as well as any other relevant information — such as the presence of 
                other Trackers — in the linked privacy policies of the respective third-party providers or by contacting the Owner.
            </p>
            
            <h2 class="cookie-policy-heading">How csstrats.dk uses Trackers</h2>
            
            <h3 class="cookie-policy-subheading">Necessary</h3>
            <p>
                Csstrats.dk uses so-called "technical" Cookies and other similar Trackers to carry out activities that are strictly 
                necessary for the operation or delivery of the Service.
            </p>
            
            <h4 class="cookie-policy-subheading-small">Trackers managed by third parties</h4>
            
            <div class="cookie-policy-card">
                <h5 class="cookie-policy-card-title">Stripe (Stripe Payments Europe, Limited)</h5>
                <p>Stripe is a payment service provided by Stripe Payments Europe, Limited.</p>
                <p><strong>Personal Data processed:</strong> email address, Trackers, Usage Data and various types of Data as specified in the privacy policy of the service.</p>
                <p><strong>Place of processing:</strong> European Union – <a href="https://stripe.com/ie/privacy" target="_blank" rel="noopener noreferrer" class="cookie-policy-link">Privacy Policy</a>.</p>
                <p><strong>Trackers duration:</strong></p>
                <ul class="cookie-policy-list">
                    <li>1: indefinite</li>
                    <li>__Host-LinkSession: 2 years</li>
                    <li>__stripe_mid: 1 year</li>
                    <li>__stripe_sid: 30 minutes</li>
                    <li>_mf: indefinite</li>
                    <li>dashboard.banner-dismissals: duration of the session</li>
                    <li>link.auth_session_client_secret: duration of the session</li>
                    <li>m: 2 years</li>
                    <li>pay_sid: 1 year</li>
                </ul>
            </div>
            
            <h3 class="cookie-policy-subheading">Experience</h3>
            <p>
                Csstrats.dk uses Trackers to improve the quality of the user experience and enable interactions with external content, 
                networks and platforms.
            </p>
            
            <h4 class="cookie-policy-subheading-small">Trackers managed by third parties</h4>
            
            <div class="cookie-policy-card">
                <h5 class="cookie-policy-card-title">Google Fonts</h5>
                <p>
                    Google Fonts is a typeface visualization service provided by Google LLC or by Google Ireland Limited, 
                    depending on how the Owner manages the Data processing, that allows csstrats.dk to incorporate content 
                    of this kind on its pages.
                </p>
                <p><strong>Personal Data processed:</strong> Trackers and Usage Data.</p>
                <p><strong>Place of processing:</strong> United States – <a href="https://business.safety.google/privacy/" target="_blank" rel="noopener noreferrer" class="cookie-policy-link">Privacy Policy</a>; Ireland – <a href="https://business.safety.google/privacy/" target="_blank" rel="noopener noreferrer" class="cookie-policy-link">Privacy Policy</a>.</p>
            </div>
            
            <h3 class="cookie-policy-subheading">Measurement</h3>
            <p>
                Csstrats.dk uses Trackers to measure traffic and analyse User behaviour to improve the Service.
            </p>
            
            <h4 class="cookie-policy-subheading-small">Trackers managed directly by the Owner</h4>
            
            <div class="cookie-policy-card">
                <h5 class="cookie-policy-card-title">UXwizz (self-hosted) (csstrats.dk)</h5>
                <p>
                    UXwizz is a self-hosted analytics software used by csstrats.dk to analyze usage data directly without the help of third parties.
                </p>
                <p><strong>Personal Data processed:</strong> Usage Data.</p>
            </div>
            
            <h3 id="manage_cookies" class="cookie-policy-subheading">How to manage preferences and provide or withdraw consent on csstrats.dk</h3>
            <p>
                On your first visit, csstrats.dk shows a consent banner where you choose whether to allow analytics.
                Essential cookies (needed to sign in, keep your session, and remember your choice) are always on and
                do not require consent. Your decision is stored in a single first-party cookie, <strong>cs_consent</strong>,
                for up to 180 days.
            </p>
            <p>
                You can change or withdraw your choice at any time:
            </p>
            <button type="button" class="consent-cta" onClick="window.csConsent&&window.csConsent.open();return false;">
                Manage cookie settings
            </button>
            <p>
                Declining analytics stops the UXwizz tracker from loading; granting it loads the tracker immediately,
                with no page reload needed.
            </p>
            
            <h4 class="cookie-policy-subheading-small">How to control or delete Cookies and similar technologies via your device settings</h4>
            <p>Users may use their own browser settings to:</p>
            <ul class="cookie-policy-list">
                <li>See what Cookies or other similar technologies have been set on the device;</li>
                <li>Block Cookies or similar technologies;</li>
                <li>Clear Cookies or similar technologies from the browser.</li>
            </ul>
            <p>The browser settings, however, do not allow granular control of consent by category.</p>
            
            <p>Users can, for example, find information about how to manage Cookies in the most commonly used browsers at the following addresses:</p>
            <ul class="cookie-policy-list">
                <li><a href="https://support.google.com/chrome/answer/95647?hl=en&p=cpn_cookies" target="_blank" rel="noopener nofollow" class="cookie-policy-link">Google Chrome</a></li>
                <li><a href="https://support.mozilla.org/en-US/kb/enable-and-disable-cookies-website-preferences" target="_blank" rel="noopener nofollow" class="cookie-policy-link">Mozilla Firefox</a></li>
                <li><a href="https://support.apple.com/guide/safari/manage-cookies-and-website-data-sfri11471/" target="_blank" rel="noopener nofollow" class="cookie-policy-link">Apple Safari</a></li>
                <li><a href="http://windows.microsoft.com/en-us/windows-vista/block-or-allow-cookies" target="_blank" rel="noopener nofollow" class="cookie-policy-link">Microsoft Internet Explorer</a></li>
                <li><a href="https://support.microsoft.com/en-us/help/4027947" target="_blank" rel="noopener nofollow" class="cookie-policy-link">Microsoft Edge</a></li>
                <li><a href="https://support.brave.com/hc/en-us/articles/360022806212-How-do-I-use-Shields-while-browsing" target="_blank" rel="noopener nofollow" class="cookie-policy-link">Brave</a></li>
                <li><a href="https://help.opera.com/en/latest/web-preferences/#cookies" target="_blank" rel="noopener nofollow" class="cookie-policy-link">Opera</a></li>
            </ul>
            
            <p>
                Users may also manage certain categories of Trackers used on mobile apps by opting out through relevant device settings 
                such as the device advertising settings for mobile devices, or tracking settings in general (Users may open the device 
                settings and look for the relevant setting).
            </p>
            
            <h4 class="cookie-policy-subheading-small">Consequences of denying the use of Trackers</h4>
            <p>
                Users are free to decide whether or not to allow the use of Trackers. However, please note that Trackers help csstrats.dk 
                to provide a better experience and advanced functionalities to Users (in line with the purposes outlined in this document). 
                Therefore, if the User chooses to block the use of Trackers, the Owner may be unable to provide related features.
            </p>
            
            <h3 id="owner_of_the_data" class="cookie-policy-subheading">Owner and Data Controller</h3>
            <p>Asger Spange - Finlandgade 9 2. 2 Odense</p>
            <p><strong>Owner contact email:</strong> asger@csstrats.dk</p>
            
            <p>
                Since the use of third-party Trackers through csstrats.dk cannot be fully controlled by the Owner, any specific references 
                to third-party Trackers are to be considered indicative. In order to obtain complete information, Users are kindly requested 
                to consult the privacy policies of the respective third-party services listed in this document.
            </p>
            
            <p>
                Given the objective complexity surrounding tracking technologies, Users are encouraged to contact the Owner should they wish 
                to receive any further information on the use of such technologies by csstrats.dk.
            </p>
            
            <div class="cookie-policy-definitions">
                <h3 class="cookie-policy-subheading">Definitions and legal references</h3>
                
                <h4 class="cookie-policy-subheading-small">Personal Data (or Data)</h4>
                <p>
                    Any information that directly, indirectly, or in connection with other information — including a personal identification 
                    number — allows for the identification or identifiability of a natural person.
                </p>
                
                <h4 class="cookie-policy-subheading-small">Usage Data</h4>
                <p>
                    Information collected automatically through csstrats.dk (or third-party services employed in csstrats.dk), which can include: 
                    the IP addresses or domain names of the computers utilized by the Users who use csstrats.dk, the URI addresses (Uniform Resource 
                    Identifier), the time of the request, the method utilized to submit the request to the server, the size of the file received in 
                    response, the numerical code indicating the status of the server's answer (successful outcome, error, etc.), the country of origin, 
                    the features of the browser and the operating system utilized by the User, the various time details per visit (e.g., the time spent 
                    on each page within the Application) and the details about the path followed within the Application with special reference to the 
                    sequence of pages visited, and other parameters about the device operating system and/or the User's IT environment.
                </p>
                
                <h4 class="cookie-policy-subheading-small">User</h4>
                <p>The individual using csstrats.dk who, unless otherwise specified, coincides with the Data Subject.</p>
                
                <h4 class="cookie-policy-subheading-small">Data Subject</h4>
                <p>The natural person to whom the Personal Data refers.</p>
                
                <h4 class="cookie-policy-subheading-small">Data Processor (or Processor)</h4>
                <p>
                    The natural or legal person, public authority, agency or other body which processes Personal Data on behalf of the Controller, 
                    as described in this privacy policy.
                </p>
                
                <h4 class="cookie-policy-subheading-small">Data Controller (or Owner)</h4>
                <p>
                    The natural or legal person, public authority, agency or other body which, alone or jointly with others, determines the purposes 
                    and means of the processing of Personal Data, including the security measures concerning the operation and use of csstrats.dk. 
                    The Data Controller, unless otherwise specified, is the Owner of csstrats.dk.
                </p>
                
                <h4 class="cookie-policy-subheading-small">csstrats.dk (or this Application)</h4>
                <p>The means by which the Personal Data of the User is collected and processed.</p>
                
                <h4 class="cookie-policy-subheading-small">Service</h4>
                <p>The service provided by csstrats.dk as described in the relative terms (if available) and on this site/application.</p>
                
                <h4 class="cookie-policy-subheading-small">European Union (or EU)</h4>
                <p>
                    Unless otherwise specified, all references made within this document to the European Union include all current member states 
                    to the European Union and the European Economic Area.
                </p>
                
                <h4 class="cookie-policy-subheading-small">Cookie</h4>
                <p>Cookies are Trackers consisting of small sets of data stored in the User's browser.</p>
                
                <h4 class="cookie-policy-subheading-small">Tracker</h4>
                <p>
                    Tracker indicates any technology - e.g Cookies, unique identifiers, web beacons, embedded scripts, e-tags and fingerprinting - 
                    that enables the tracking of Users, for example by accessing or storing information on the User's device.
                </p>
                
                <h4 class="cookie-policy-subheading-small">Legal information</h4>
                <p>This privacy policy relates solely to csstrats.dk, if not stated otherwise within this document.</p>
            </div>
            
            <div class="cookie-policy-footer">
                <p>Latest update: March 17, 2025</p>
            </div>
        </div>
    </div>
</div>

@endsection