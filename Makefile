VERSION=0.1
RELEASE_FILE=jam_ext_settings$(VERSION).tar

SOURCES =
SOURCES += extensions/ext.jam_ext_settings.php
SOURCES += extensions/ext.jam_iframe_tab.php
SOURCES += language/english/lang.jam_ext_settings.php
SOURCES += language/english/lang.jam_iframe_tab.php

install: $(RELEASE_FILE)
	@echo "install complete $(RELEASE_FILE)"

$(RELEASE_FILE) : $(SOURCES)
	tar cvf $@ $(SOURCES)

