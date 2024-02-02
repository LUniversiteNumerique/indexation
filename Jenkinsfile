node {
  stage('SCM') {
    checkout scm
  }
  stage('SonarQube Analysis') {
    agent {
      docker {
        image 'sonarsource/sonar-scanner-cli:4.6' args '-v $PWD:/usr/src'
      }
    }
    steps {
      withSonarQubeEnv('SonarQube SSL') {
        script {
          // Execution de l'analyse sonar
          sh 'sonar-scanner -Dsonar.projectKey=$SONARPROJECTKEY -Dsonar.projectVersion=$BUILDVERSION -Dproject.settings=sonar-project.properties'
        }
      }
    }
  }
  stage('Checkmarx analysis') {
			steps{
				// Creating the folder and data for analysis
				dir('checkmarx') {
					// Deleting the previous analysis data
					sh "rm -rf * && ls -la"
					// Copy what we want to send to analysis
					sh "cp -R ../data-middleware/ ./data-middleware"
					sh "cp -R ../data-top-braid-ws/ ./data-top-braid-ws"
					sh "cp -R ../data-es-client/ ./data-es-client"
					sh "cp -R ../data-keycloak-client/ ./data-keycloak-client"
					echo "FOLDERS TO ANALYSE"
					// Displaying the content of the folder
					sh "ls -la"
				}
				dir('checkmarx') {
					script{
						runCheckmarxAnalysis();
					}
				}
				dir('checkmarx/Checkmarx/Reports') {
					// Archives the build artifacts (for example, distribution zip files or jar files)
					// so that they can be downloaded later. Archived files will be accessible from the Jenkins webpage.
					archiveArtifacts "CxSASTReport*.pdf"
			}
		}
	}
}
def runCheckmarxAnalysis() {
  // See : https://checkmarx.atlassian.net/wiki/spaces/SD/pages/965050901/Configuring+a+CxSAST+Scan+Action+using+Jenkins+Pipeline+v8.9.0+and+up
  step([
      $class: 'CxScanBuilder',
      comment: '',
      credentialsId: 'checkmarx_credentials', // Defined in the settings of Jenkins
      excludeFolders: '',
      excludeOpenSourceFolders: '',
      exclusionsSetting: 'global',
      failBuildOnNewResults: false,
      failBuildOnNewSeverity: 'HIGH',
      filterPattern: '''!**/_cvs/**/*, !**/.svn/**/*,   !**/.hg/**/*,   !**/.git/**/*,  !**/.bzr/**/*, !**/bin/**/*,
          !**/obj/**/*,  !**/backup/**/*, !**/.idea/**/*, !**/*.DS_Store, !**/*.ipr,     !**/*.iws,
          !**/*.bak,     !**/*.tmp,       !**/*.aac,      !**/*.aif,      !**/*.iff,     !**/*.m3u, !**/*.mid, !**/*.mp3,
          !**/*.mpa,     !**/*.ra,        !**/*.wav,      !**/*.wma,      !**/*.3g2,     !**/*.3gp, !**/*.asf, !**/*.asx,
          !**/*.avi,     !**/*.flv,       !**/*.mov,      !**/*.mp4,      !**/*.mpg,     !**/*.rm,  !**/*.swf, !**/*.vob,
          !**/*.wmv,     !**/*.bmp,       !**/*.gif,      !**/*.jpg,      !**/*.png,     !**/*.psd, !**/*.tif, !**/*.swf,
          !**/*.jar,     !**/*.zip,       !**/*.rar,      !**/*.exe,      !**/*.dll,     !**/*.pdb, !**/*.7z,  !**/*.gz,
          !**/*.tar.gz,  !**/*.tar,       !**/*.gz,       !**/*.ahtm,     !**/*.ahtml,   !**/*.fhtml, !**/*.hdm,
          !**/*.hdml,    !**/*.hsql,      !**/*.ht,       !**/*.hta,      !**/*.htc,     !**/*.htd, !**/*.war, !**/*.ear,
          !**/*.htmls,   !**/*.ihtml,     !**/*.mht,      !**/*.mhtm,     !**/*.mhtml,   !**/*.ssi, !**/*.stm, !**/*.sql,
          !**/*.stml,    !**/*.ttml,      !**/*.txn,      !**/*.xhtm,     !**/*.xhtml,   !**/*.class, !**/*.iml, !Checkmarx/Reports/*.*''',
      fullScanCycle: 5,
      fullScansScheduled: "${env.INCREMENTAL_CHECKMARX_ANALYSIS}",
      generatePdfReport: true,
      groupId: 'ab1a2b53-8b95-4c7e-90e2-37a128f91c99',
      includeOpenSourceFolders: '',
      incremental: "${env.INCREMENTAL_CHECKMARX_ANALYSIS}",
      osaArchiveIncludePatterns: '*.zip,*.war,*.ear,*.tgz',
      osaInstallBeforeScan: false,
      password: '',
      preset: '36',
      projectName: "$SONARPROJECTKEY",
      sastEnabled: true,
      serverUrl: "${env.CHECKMARX_HOST}",
      sourceEncoding: '1',
      useOwnServerCredentials: true,
      username: '',
      vulnerabilityThresholdResult: 'FAILURE',
      waitForResultsEnabled: true
  ])
}


