=== Go Fetch Jobs (for WP Job Manager) ===
Author: SebeT
Contributors: SebeT, freemius
Tags: rss, feed, jobs, WP Job Manager, automate
Requires at least: 5.5
Requires PHP: 7.4.1
Tested up to: 6.7
Stable tag: 1.8.4.8

Instantly populate your WP Job Manager database using RSS job feeds from the most popular job sites or load XML/JSON files (premium only).

== Description ==

> [Official site](http://gofetchjobs.com) / [DEMO site](https://demo.gofetchjobs.com/)

> Premium versions (with Trial) now available for:
- [Workio](https://themeforest.net/item/workio-job-board-wordpress-theme/26699370/) ([Trial](https://gofetchjobs.com/pricing))
- [Workup](https://themeforest.net/item/workup-job-board-wordpress-theme/24261784/) ([Trial](https://gofetchjobs.com/pricing))
- [WP Job Openings](https://wordpress.org/plugins/wp-job-openings/) ([Trial](https://gofetchjobs.com/pricing))
- [Simple Job Board](https://wordpress.org/plugins/simple-job-board/) ([Trial](https://gofetchjobs.com/pricing))

Instantly populate your [WP Job Manager](https://wpjobmanager.com/) site with jobs from the most popular job sites and/or job aggregators. This handy plugin fetches jobs from RSS feeds and imports them to your jobs database. Pick your favorite job site/job directory, look for the jobs RSS feed, paste it directly to *Go Fetch Jobs* and instantly get fresh jobs in your database!

To help you quickly getting fresh jobs from external sites, *Go Fetch Jobs* comes bundled with ready to use RSS feeds and detailed instructions on how to setup RSS feeds for job sites that provide them.

Easily categorize bulk job imports with job categories, job types, default custom fields values and expiry dates.

Besides the usual *Title* + *Description* + *Date* usually provided by RSS feeds, *Go Fetch Jobs* can also (optionally) extract and auto fill job companies logos, company names and locations, if that information is provided by the RSS feed.

It also comes with the ability to save import rules as templates so you can later recycle/re-use them for new imports.

Upgrade to a *Premium* plan to keep it automatically updated with fresh jobs added every day, through automatic schedules! (*)

> #### Features include:
>
> * Ready to use RSS Feeds from popular providers, including [jobs.theguardian.com](jobs.theguardian.com), with detailed setup instructions
> * Import jobs from any valid RSS feed
> * Seamless integration with WPJM jobs
> * Assign job expiry date
> * Save import rules as templates
> * Company logos on select providers
> * Company names and job locations on select providers
>
> * And more...

> #### Additional features, exclusive to *Premium* plans include:
>
> * Ready to use RSS feeds from popular job sites including: *[adzuna.com](adzuna.com) and [careerjet.com](careerjet.com)*
> * Import jobs from custom XML/JSON files
> * Import jobs from local files
> * Import jobs from *AdZuna, SourceStack, WhatJobs, Careerjet, Talent.com and many others
> * Custom RSS builder for select providers that allows creating custom RSS feeds with specific keywords/location
> * Automated scheduled imports
> * Positive and negative keyword filtering
> * Job Types/Categories mappings for Smart Assign
> * Ability to extract incomplete/missing meta data directly from provider site on select providers (can extract full job descriptions, companies, locations and logos - e.g: Indeed)
> * 'Test' or 'Run' schedules directly from the schedules page
> * Set your own time interval between schedule runs
> * Extract and auto-fill job company names and locations on select providers
> * Auto assign job types and job categories based on each job content
>
> * And more...

Visit the [official website](http://gofetchjobs.com) for more details on features and available plans.

(*) You can upgrade to any plan directly from the plugin.

== Installation ==

1. Extract the zip file and just drop the contents in the wp-content/plugins/ directory of your WordPress installation and then activate the Plugin from the Plugins page.
2. A new Menu named 'Go Fetch Jobs' will be available on the left sidebar.

== Frequently Asked Questions ==

= How do I activate my premium plan after purchase ? =
After your purchase, deactivate the Free version and download and activate the premium version. Under *Go Fetch Jobs > Account*, click 'Sync' or the *Activate Plan* button.
If the premium plan is not activated immediately please try again later since it can take a few minutes until the server is updated.

== Screenshots ==

1. Existing RSS Providers List
2. Load Saved Import Templates
3. RSS Feed Setup Detailed Instructions
4. Custom RSS Feed Builder
5. Fetch Job Companies Logos
6. Set Job Providers Information / Optional URL Parameters
7. Set Job Details for Imported Jobs
8. Filters / Save Templates
9. Job Listings for Imported Jobs (Frontend)
10. Single Job Page for Imported Jobs (Frontend)
11. Settings Page
12. Jobs Filter & Provider Column

== Changelog ==

1.8.4.8.1
changes
	- Updated Freemius SDK
fixes
	- Fixed PHP warnings

1.8.4.8
Changes:
	- 'WhatJobs' provider is now a top partner (includes full job descriptions)
	- Additional internal formatting rules for plain text descriptions
	- Limit schedules recurrence options by provider
	- Display provider ID on the schedule page

fixes:
	- Monthly and Weekly schedules running every hour on the first day of the month/week
	- Schedules 'next run time' not showing valid times

1.8.4.7.1
Changes:
	- 'WhatJobs' provider is now a top partner (includes full job descriptions)
	- Additional internal formatting rules for plain text descriptions
	- Limit schedules recurrence options by provider
	- Display provider ID on the schedule page

fixes:
	- Monthly and Weekly schedules running every hour on the first day of the month/week
	- Schedules 'next run time' not showing valid times

1.8.4.7
Changes:
	- Added new API provider: Appcast (https://www.appcast.io/)
	- Removed provider: Krop (no longer provides RSS feeds)

fixes:
	- API Key for 'The Muse' provider not being saved correctly

1.8.4.6.2
fixes:
	- Some users getting a fatal error on plugin activation

1.8.4.6.1
fixes:
	- Some users getting a fatal error on plugin activation, or running schedules

1.8.4.6
changes:
	- Added 'AdZuna' eligibility check, for 'AdZuna' API applications
	- Add 'JOB DATE' as an assignable mapping field

fixes:
	- PHP warnings

1.8.4.5.1
changes:
	- (SourceStack) New 'Must Provide Logo' filter, to allow only importing jobs that contain company logo
	- Better handling of complex RSS feed URL's when saving/loading templates

fixes:
	- (SourceStack) Templates not being saved correctly

1.8.4.5
changes:
	- Added new option 'Upload Company Logos' to allow uploading company logos to the media library
	- Flag 'Remotive' as supporting full job descriptions

fixes:
	- Keywords filtering on Schedules not working as expected
	- Smart assign not always working as expected
	- PHP warnings

1.8.4.4
changes:
	- Updated SourceStack internal filtering for better results

fixes:
	- Fatal error when pulling full job descriptions using CV-Library API, when job ID is not found
	- XML files containing nodes with multiple child nodes not being properly loaded
	- PHP warnings when using the 'jobs' shortcode related with uninitialized vars
	- SQL error being triggered on some specific situations when the dynamic title query is missing

1.8.4.3
changes:
	- (SourceStack) Use SourceStack's URL request structure for the feed builder

fixes:
	- (SourceStack) Country filter not working as expected
	- Keywords filtering not always working as expected
	- Keywords filtering applied whenever positive/negative keywords were filled, even if the checkbox 'Include/Exclude jobs based on their keywords' was unchecked

1.8.4.2
changes:
	- (SourceStack) prefer job descriptions HTML
	- (SourceStack) improve internal filtering

fixes:
	- Some invalid image URL's showing because of invalid spaces
	- Schedules sometimes failing due to missing dependencies
	- Setting an empty jobs duration causing jobs to not show on frontend (wpjm only)

1.8.4.1
changes:
	- Added support for XML files that use generic <Field> tags identified by the 'name' attribute

fixes:
	- Custom mapping of job title causing duplicates jobs
	- Sample table remote/featured fields were switched internally

1.8.4
changes:
	- Added new multi-country provider: WhatJobs
	- (SourceStack) Updated job categories list
	- (SourceStack) Added 'Region' option to filters
	- (AdZuna) Added two new filters: Keywords Comparison and Job Type
	- (JazzHR) Updated instructions to find job board code and removed unused 'subdomain' setting
	- Added new 'Paragraph Line Breaks' option
	- Better job descriptions formatting

fixes
	- (JazzHR) Using wrong API URL parameters
	- HTML tag being added to job descriptions

1.8.3
changes:
	- Introduce new partner API provider: SourceStack (https://sourcestack.co/)

1.8.2.2
fixes
	- Fatal error occurring on some specific XML files structures
	- Security issue with Freemius SDK

1.8.2.1
fixes
	- Fields missing on some providers feeds
	- Image placeholder showing when company logo not available
	- Fatal error being thrown for some users, when sanitizing mappings

1.8.2
changes
	- Better handling of complex XML files
	- Added estimated 'next run time' to schedules
	- Added new parameter 'country' to [gofj_jobs] shortcode (allows listing jobs from specific countries) - (applied to new jobs only - WPJM only)
	- Added country search support for imported jobs, from a multi-region pre-set provider (applied to new jobs only - WPJM only)
	- Selecting a category for AdZuna API is now optional
	- Display alert when there are unmapped core fields
	- Display missing required URL parameters when loading a feed from a pre-set provider
	- Display missing API fields when loading an API feed from a pre-set provider
	- Introduce new option for defaulting imported job dates to the feed date (default) or the current date
	- Added support for WPJM's new Remote positions option
	- Added new filters to allow overriding tags on custom xml files
	- Removed provider: https://jobsearch.monster.com.hk - changed name, no longer provides RSS feeds
	- Removed provider: https://www.jobs.ac.uk - no longer provides RSS feeds
	- Removed provider: https://www.indeed.com - no longer provides RSS feeds
	- Removed 'OpenGraph' dependency
	- Updated Freemius SDK

fixes
	- AdZuna results limits and pagination not working as expected (returning only 10 results when limit not specified)
	- Updated 'The Guardian Jobs' parsing rules. Fixes some missing metadata.
	- 'SalesJobs' feed URL sometimes being replaced by a different provider feed URL
	- PHP warnings on PHP 8.0

1.8.1.2
changes
	- Better handling of complex XML files that use attributes in tags
	- Updated AdZuna logo asset
	- Auto disable free version of the plugin when installing paid version

fixes
	- Job page not displaying properly when using 'WP Job Manager - Resume Manager' plugin
	- Fatal error when using plugins that use the 'wp_kses_allowed_html' filter (i.e: Breadcrumbs NavXT)
	- Extra comma causing fatal error on old PHP versions
	- Guided tutorial showing during Freemius opt-in

1.8.1.1
changes
	- Added support for loading custom XML feeds from CV-Library  (e.g: https://www.cv-library.co.uk/cgi-bin/feed.xml?)
	- Display info box when feed does not provide full job descriptions

fixes
	- Error 'change_job_manager_delete_expired_jobs_days' already declared
	- Schedules page, not properly rendering the fields, on new schedules
	- Templates for multi-country providers not correctly saving the selected country
	- Missing 'locale_code' parameter on Careerjet API URL
	- Anchor attributes being stripped from content

1.8.1
changes
	- Introduce new RSS feed provider for remote jobs - euroremotejobs.com
	- Deprecated Indeed's RSS feed since Indeed is now actively blocking all http requests to the feed
	- Added support for loading gzipped XML files (on Professional and Business plans)
	- Added more visibility to new Go Fetch Jobs partner providers
	- Grouped partners on dedicated groups, on the providers dropdown

fixes
	- Sample table being automatically hidden after loading a XML/JSON file
	- PHP warnings showing up on some situations

1.8.0
changes
	- Added support for filling the new WPJM 'Salary' field, when available
	- Added support for scraping 'Salary' if available on the provider page (pre-set providers only)
	- Added option to auto scrape full job description, to settings page
	- Added option to hide the job source on job pages, to the settings page
	- Added option to select all/unselect all scrape fields, on import page
	- Added support for 'MAS Companies for WP Job Manager' plugin (auto populates job Company dropdown)
	- Added new option to redirect users to application URL, on click, instead of displaying application URL (WPJM only)
	- Added randomized headers for better scraping
	- Added additional retries for providers that sometimes return invalid content
	- Updated all pre-set providers to pull in as much metadata as possible
	- Auto cleanup job titles that contain additional metadata information (for select providers) (i.e: "Sales Manager - Google - Mountain View" converts to "Sales Manager")
	- Introduce new multi-region RSS provider: https://www.jobmonkeyjobs.com
	- Removed 'Search Embed Logos' option (ignored by most providers)
	- Removed 'remotive.io' RSS provider due to limited content and metadata
	- Removed 'stackoverflow jobs' since it was discontinued
	- Removed 'github jobs' since it was discontinued

fixes
	- Database error being thrown when using custom table prefixes
	- Schedules not honoring the settings start time
	- Country not being auto-loaded, for 'Indeed France' templates
	- Application details showing when 'Apply for Job' click was set to redirect
	- 'Read More' showing for some jobs with full job descriptions

1.7.3.2.5
fixes
	- Import button not working (Free version only)

1.7.3.2.4
fixes:
	- Security fixes

1.7.3.2
changes:
	- Added new API provider: https://www.themuse.com/jobs (Optional API Key)
	- Added new API provider: https://www.workingnomads.com/jobs (no API key required)
	- Aded option to automatically create schedules during import, is now available, after creating a new template
	- Removed GitHub API (no longer available)
	- Added support for filtering jobs within all available feed fields

fixes:
	- Multiple selection options displaying as checkboxes, on the settings page
	- Fields on more complex XML files not read correctly

1.7.3.1
changes:
	- Added full scraping functionality to Remotive.io provider
	- Better handling of JSON files with more than one parent node

fixes:
	- HTML tags being stripped from job descriptions when mapping non-standard <description> tags
	- Shortcode not filtering imported jobs
	- AdZuna import pagination not working
	- Some JSON/XML sources being displayed on the single job page

1.7.3
changes:
	- Introduce new RSS provider: remotive.io (remote jobs)
	- Introduce new API provider: remotive.io (remote jobs) - free to use on Professional/Business plans - no API key required
	- Introduce new API provider: JobtomeAds (multi-region, multi-industries jobs)
	- Introduce new option to set default/placeholder company logo for imported jobs without a logo
	- Introduce new option to replace existing jobs from the same feed
	- Better handling of XML nodes
	- Better handling of unique logo filenames
	- Better formatting of job descriptions
	- Removed scraping options for Jooble API (no longer supported by Jooble)

fixes:
	- Use alternative 'mb_convert_encoding()' helper, in case PHP extension is not installed
	- Override WP 'wpautop()' to avoid removing line breaks from job descriptions
	- Careerjet scraper not pulling the correct metadata
	- Taxonomy field mappings not being honored when mapped to an empty field
	- Taxonomy field not defaulting to the default term, when mapped and content was empty
	- Templates (internally) being loaded twice
	- Extra double quote on GOFJ stats logo causing logo failing to display

1.7.2.1.1
fixes:
	- Javsacript issues causing unexpected behavior

1.7.2.1
changes:
	- Added support for auto-deleting expired jobs
	- Allow filtering job import authors list
	- Added support for scraping lazyloaded logos
	- Use json_decode()/json_decode() as primary option for loading XML feeds (provides wider XML support)

fixes:
	- Talent.com RSS feeds being wrongly indentified as Talent.com API feeds

1.7.2
changes:
	- Added support for JazzHR ATS (Business plans only)
	- Added 'Colombia' to Careerjet's API list of countries

fixes:
	- Possible mismatched company logos when using providers that re-use the company logo filename (ex: logo.gif)

1.7.1.1.1
fixes:
	- Double forward slashes on feeds query strings, being replaced by a single forward slash
	- Location empty when using 'Astoundify Job Manager Regions' plugin

1.7.1.1
changes:
	- added 'Job Category' field for AdZuna, on the feed builder
	- better JSON files support

fixes:
	- DivisonByZero error when the 'Paragraphs Rule' settibg was set to 0

1.7.1
changes:
	- Added provider logos to the respective API/ATS settings page
	- Added support for new API provider: Adzuna
	- Added support for ATS providers (Business plans only): Greenhouse and Recruitee
	- Renamed 'Providers' menu item to 'API Providers'

fixes:
	- Simple structured XML files not loading correctly
	- (JobCareer theme) default fields could cause jobs to not display
	- (Cariera theme) Do now fill application URL field, if job application contains an email

1.7.0.4
changes:
	- Allow selecting which XML tag that is mapped to the company logo field
	- Added support for XML files with nested nodes
	- Hide import type toggle during job fetching

fixes;
	- (Cariera theme) Alternative application field not being auto-filled
	- Internal PHP warnings being generated during scheduled imports

1.7.0.3
changes:
	- Added new remote jobs provider: Jobicy.com
	- Removed provider: careerjet.com. No longer provides RSS feeds

fixes:
	- Job titles with amperstands (&) not being caught during duplicates check
	- XML files with multi-level deep nested nodes not being properly loaded
	- File/URL picker not being correctly toggled internally

1.7.0.2.2
fixes:
	- Critical 'wp_robots_noindex()' error

1.7.0.2.1
fixes:
	- Critical errors related with invalid 'wp_robots_no_robots()' calls

1.7.0.2
fixes:
	- Custom XML imports showing invalid mappings when they contained non-nested nodes
	- Replaced deprecated 'wp_no_robots()' calls with 'wp_robots_no_robots()'
	- Duplicate check on jobs containing quotes in the title could fail

1.7.0.1
changes:
	- Added full for support for Cariera WPJM theme (https://themeforest.net/item/cariera-job-board-wordpress-theme/20167356)
	- Updated Careerjet API scrapper

fixes:
	- Keyword matching not being applied to scraped full job description
	- Duplicate jobs showing up with some language codes

1.7.0
changes:
	- Added support for loading custom XML files (Professional and Business plans only)
	- Added support for loading custom JSON files (Professional and Business plans only)
	- Added support for loading local XML and JSON files (Professional and Business plans only)
	- Reritten duplicates checker algorithm for faster and more accurate results
	- Reviewed and updated all providers
	- Added 'HigherEdJobs' provider
	- Added 'CrunchBoard' provider
	- Removed 'Update' option from 'Duplicates Behavior' - it was not reliable
	- Removed 'JobsInNenya' provider - no longer provides RSS feeds
	- Removed 'CraigsList' provider - no longer provides RSS feeds
	- Removed 'AdView' API provider (no longer available)

fixes:
	- 'Monster' jobs descriptions scraper generating duplicate descriptions
	- Some company logos displaying as empty images
	- Feeds pagination

1.6.7.1
changes:
	- Updated Nuevoo's API (now Talent.com)
	- Removed canonical links option (no longer used)

fixes:
	- Old schedules still using daily recurrence instead of hourly (hourly schedules would not run)
	- Some funcionality, like updating jobs with additional metadata, not running on schedules
	- Feeds that provide pagination, failing to retrieve more jobs than the pre-set limit, when keywords or locations were missing

1.6.6.1
changes:
	- Look for duplicate jobs inside feeds

fixes:
	- Some jobs being duplicated during feed pagination

1.6.6
changes:
	- Introduce new RSS feed provider: https://remotewoman.com/
	- Introduce new Ideas / Suggestions page
	- Updated Jobs2Careers to Talroo
	- Added logo support to Talroo
	- Temporarily disabled ZipRecruiter (to be re-added on a future update)
	- Ignore expiry date when saving template settings
	- Importer page UI changes

fixes:
	- Expiration duration not being honored on saved tempaltes
	- HTML tags not being properly encoded and showing up on job descriptions
	- Duplicate jobs being imported when selecting 'Update' on 'Duplicates Behavior' option
	- Invalid Craigslist region domains
	- Company logo URL's containing the host name but no http scheme not being correctly returned

1.6.5
changes:
	- Added country/state selector for multi-region providers: Indeed, Careerjet, Jooble and Craigslist. It's now easier then ever to choose your Country feed.
	- Added support for Jooble API (available in 70 countries!)
	- Added support for JuJu API (over 5,500,000 jobs available!)
	- Added support for choosing specific country feeds, on multi-country providers (e.g: indeed, jooble, etc)
	- Added support for editing saved templates using the feed builder (applicable only for new templates)
	- Introduce new 'Smart Assign > Create Terms' setting to  enable/disable automatically creation of terms, when using 'Smart Assign'
	- Reduce feed cache duration when 'Duplicates Behavior' is set to 'Update', to make sure feed data is fresh
	- Removed 'AdView API aka WhatJobs' since it no longer provides API or RSS feeds

fixes:
	- New terms being automatically created when mapping a taxonomy field
	- Reduce importer cache duration to avoid duplicates being created
	- Screen options not showing
	- Importer mode stuck in 'Basic' mode

1.6.4.3
fixes:
	- Importer incorrectly creating duplicate jobs (regression)

1.6.4.2
fixes:
	- Schedule page not reloading after a test or manual run

1.6.4.1
changes:
	- Added link to feed generator website: https://rss.app/ on import page
	- Added nopener noreferrer to external links
	- Added support for AND/OR comparisons on Postive/Negative keywords filtering
	- Better handling of Negative/Positive keywords filtering

fixes:
	- Duplicate jobs being created if the same jobs were found with different content
	- CV-Library feed builder fields not showing up

1.6.4
changes:
	- Added new API provider: 'jobs.github.com/api'
	- Added new RSS providers: 'whatjobs.com', 'creativejobscentral.com', 'healthcareercenter.com', 'hospitalcareers.com'
	- Added new RSS providers: 'jobsinkenya.co.ke (Jobs in Kenya)',' myjobmag.com (Jobs in Ghana, Kenya, Nigeria and South Africa)'
	- Removed provider 'mediabistro.com' (no longer providers RSS feeds)
	- Added support for full-descriptions, on 'CV-Library' provider
	- Added support for 'searchon' option to search keywords by title or employes name, on 'Neuvoo' provider
	- Added support for 'all' option on job type (sponsored/organic/all)
	- Added support for 'company' parameter on [goft_jobs] shortcode
	- Revised and updated ALL providers scraping and field mapping abilities

fixes:
	- Screen options not saving
	- Provider details in 'Screen Options' not toggling
	- PHP warnings sometimes showing on single job pages
	- Tooltips positioning
	- Deprecated 'contextual_help' warning

1.6.3.2
fixes:
	- API settings not showing for Business plan

1.6.3.1.1
fixes:
	- Job descriptions showing strange characters on sites using non-latin languages

1.6.3.1
changes:
	- Added new 'Author Roles' admin setting to limit user roles that can be assigned to imported jobs
	- Added support for 'tags', in schedules
	- Removed scraping ability from 'Monster' (no longer possible to scrape)

fixes:
	- Deprecated PHP notices

1.6.3
changes:
	- Added new provider 'remoteok' - 'https://remoteok.io/'
	- Added support for 'logo' and 'category' tags for Jobs2Careers provider
	- Updated 'Craigslist' domain name to '.org'

fixes:
	- 'Submit Resume & Apply' button showing for imported jobs when 'Resumes Manager' add-on was active
	- Backend job listings sorting not wokring when custom filters were applied
