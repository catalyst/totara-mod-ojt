# Totara OJT activity

## Whats new in OJT_9:

* Now this plugin is self-contained. All files of this plugin just need to be placed into mod/ojt/ folder of any Totara installation. 
For a GIT installation of this plugin you can now do:
```
cd TOTARA_CODE_FOLDER
git submodule add git@github.com:CatalystIT-AU/totara-mod-ojt.git mod/ojt
``` 
And then add/commit file `.gitmodules` and directory `mod/ojt` (without trailing slash!!!) to the main totara git repository:
```
git add .gitmodules mod/ojt
git commit
```
 
* Fixed custom reportbuilder source 'ojt_completion' according to Totara 9 code.
* Fixed bug with non-working marking topic item activities as Complete and as Witnessed.
* Excluded custom reportbuilder sources in this plugin from Totara core PHPUnit tests. Created separate PHPUnit tests for reportbuilder source 'ojt_evaluation'.


## Original wiki:
See https://github.com/catalyst/totara-mod-ojt/wiki for overview and help ;)

#### Credits
* Contributed to the open source community through development commissioned by Customs New Zealand :)
* Developed and maintained by the Catalyst IT Elearning team (https://catalyst.net.nz)
