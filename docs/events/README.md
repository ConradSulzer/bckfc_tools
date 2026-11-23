# Overview

Previously, creating an event generally meant cloning an older event and editing its content. That provided a starting point, but dates, times, locations, and other details could appear in several places. Every copy needed to be found and updated, and the presentation depended on how each event's content had been assembled.

The new approach keeps the convenience of reusable starting content while making event creation more deliberate and repeatable:

- **Enter shared information once.** Store event dates, tournament times, locations, and size limits on the event. Components and references in the text can reuse those values, so a change does not require hunting through the page for every mention.
- **Build with reusable sections.** Assemble the header, highlights, categories, times and locations, rules, and other content the event needs. Different tournaments can have different content without needing a completely new design.
- **Keep information and presentation consistent.** Shared settings provide a common way to describe events, and shared styles keep their sections looking like parts of the same club website.
- **Give other club tools more useful event information.** Capture club-specific details beyond the native event fields, so other tools can understand and use that information without someone having to interpret the event's written description.
- **Help anglers find the important details quickly.** Make the date, location, price, registration, and tournament objectives easy to find, especially on a phone. Keep detailed rules available without making visitors read a long document to understand the event.
- **Make next year's work easier.** Reuse saved content and current rules, then adjust them for the event. An organizer should be able to create a complete page without writing code or remembering how its styling was built.

The goal is a fast, reliable way to stand up events that are easy for volunteers to maintain and easy for anglers to understand. This guide covers the settings, tickets, and page components together as one event-creation workflow.

## How the Event Design Is Applied

The new event design uses a custom Avada layout. This new layout is applied as the default using the setting **All Events** included. All events prior to this redesign retain their previous layout (legacy layout) by individually excluding them in the new layout's settings. With this setup, new events automatically use the new layout going forward, while excluded events continue using the legacy layout. Event creators do not need to do anything to use the new layout. It is now the default. This section is just to explain how it works.

These assignments are managed under **Avada → Layouts → BCKFC Events → Layout Conditions**. To use the legacy layout for a new or existing event, open **Events → Specific Events** in the conditions dialog and click the **X** beside that event to exclude it. The X beside an already-selected condition in **Manage Conditions** removes that condition instead.

To return to the legacy layout as the default for all events, go to **Avada → Layouts → BCKFC Events → Layout Conditions**, remove **All Events** and any other inclusion conditions. With no events assigned to include the new layout, the native layout will apply again to all events, including to future events. To keep any existing events with the new layout, choose to include only those by adding them to the include list. Otherwise, review those pages separately to see if they need any changes to content to render correctly with the legacy layout.

The accompanying **bckfc_theme** is an Avada child theme that provides our shared colors, fonts, and component styles. It forms the foundation for a broader website restyle and supplies the styling for the new event layout and its components. **BCKFC Tools** provides the event settings and functionality; **bckfc_theme** provides their public-facing design.

# Creating and Managing Events

Start in **Events** in the WordPress dashboard. Create a new event or open an existing event to edit it. Creating the page involves entering the event's information and assembling the content anglers will see.

There are three main parts:

- **Native event settings:** The Events Calendar (Tribe) provides the standard event fields, including the title, dates, timezone, and event categories. Tickets are managed through the existing Tickets editor.
- **BCKFC Event Settings:** Our panel adds club-specific information such as tournament format, yearly championship series membership, locations, tournament times, size limits, and login requirements. Other club tools and reusable components can use these values, and shortcodes let you include them in event text.
- **The event content:** Use Avada Builder to assemble the page from components such as the header, highlights, categories, times and locations, and rules. Start with saved content from the Avada Library where available, then edit it for this event. Add native Avada or BCKFC components as needed.

## Native Event Settings

Begin with the standard WordPress and The Events Calendar fields. These establish the event itself before you fill in the BCKFC settings or build its page content. When starting from a copy of another event, review these fields for information carried over from the original.

### Event Title

Enter the event's name in the title field at the top of the editor. This identifies the event in WordPress and event listings. The BCKFC Header also uses this title unless you enter a separate display title in that component.

Use **Event Name + Year** for most events, such as **Trout Challenge 2027**. For **Fall N Tide and Paddlepalooza**, use **Event Name + Roman Numeral** to identify the edition instead of the year.

WordPress uses the title to generate the event's URL, for example, `trout-challenge-2027`. Check the permalink before publishing, especially when copying an older event. Changing the title does not necessarily update an existing URL.

### Event Dates and Times

In The Events Calendar settings, enter the event's start and end dates and select **All Day Event**. This is our normal setting for tournaments: fishing, awards, and travel generally occupy an angler's day. For a single-day tournament, use the same start and end date. These dates describe the event, not the registration window.

Check the **Time Zone** alongside these fields. For our Louisiana events, use **America/Chicago** so the timezone accounts for daylight saving time. Calendar listings and exports will treat the tournament as an all-day event rather than a specific time block.

Our event components and event-date shortcode use the **date** from these native fields. Enter the detailed tournament schedule (Launch, Lines In, Scales Close, and other times) in **BCKFC Event Settings → Times**. The native event fields provide the date, while BCKFC settings provide the tournament times.

### Featured Image

Set the event's **Featured Image** for use in event listings and other places that display it. The BCKFC Header has its own Featured Image and Background Image controls; choosing an image there does not set the event's native Featured Image.

### Venue and Other Native Fields

Under the new design the native **Venue** field is no longer used in the event page. This is because our events typically involve multiple locations for launching and weigh-in/awards ceremony. Under the new design we manage Launch and Weigh-In / Awards locations in the **BCKFC Event Settings** panel.

### Tickets

Use the **Tickets** editor to create the tickets being sold on the club website and set their prices, availability, and sale dates. Ticket sale dates are separate from the event's Start/End fields and the announced **Registration Closes** deadline in BCKFC settings.

The **bckfc_tools** plugin adds a new field in the Tickets settings, **BCKFC Type**. This allows us to classify tickets to help with ticket exporting as well as with finance tracking. You should pick the appropriate Type for each ticket you create. 
