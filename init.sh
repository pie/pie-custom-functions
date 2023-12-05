#!/bin/bash
read -p "What is your plugin-slug?" slug
read -p "What is your Plugin Name?" name
read -p "What is your plugin description?" description

find ./ -type f -exec sed -i -e "s/plugin_template/${slug}/g" {} \; 
find ./ -type f -exec sed -i -e "s/Pie Plugin Template/${name}/g" {} \;  
find ./ -type f -exec sed -i -e "s/PluginTemplate/${name// /}/g" {} \; 
find ./ -type f -exec sed -i -e "s/This plugin doesn't do anything, but gives you a very minimal starting template./${description}/g" {} \; 

rm -fR ./init.sh
git status