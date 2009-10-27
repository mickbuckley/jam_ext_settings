VERSION=0.1
RELEASE_FILE=jam_ext_settings$(VERSION).tar

SOURCES =
SOURCES += ext.jam_ext_settings.php
SOURCES += ext.jam_iframe_tab.php
SOURCES += lang.jam_ext_settings.php
SOURCES += lang.jam_iframe_tab.php

install: $(RELEASE_FILE)
	@echo "install complete $(RELEASE_FILE)"

$(RELEASE_FILE) : $(SOURCES)
	tar cvf $@ $(SOURCES)

