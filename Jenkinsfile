pipeline {
  agent any
  post {
    failure {
      updateGitlabCommitStatus name: 'build', state: 'failed'
    }
    success {
      updateGitlabCommitStatus name: 'build', state: 'success'
    }
    aborted {
      updateGitlabCommitStatus name: 'build', state: 'canceled'
    }
    always {
      cleanWs()
      deleteDir()
    }
  }
  options {
    gitLabConnection('SSL Gitlab')
  }

  environment {
    def tagName = tagName()
    def SONARPROJECTKEY = 'universite-numerique:lunt-indexation-notice'
    def SONARPROJECTNAME = 'Université Numérique / lunt-indexation-notice'

    PROJECT_KEY="universite-numerique"
    PROJECT_NAME="Université Numérique Indexation Notice"
    APP_KEY="indexation-notice"
    APP_NAME="Indexation Notice"
    APP_FULL_KEY="universite-numerique:indexation-notice"
    APP_FULL_SAFE_KEY="universite-numerique-indexation-notice"
    APP_FULL_NAME="Université Numérique / Indexation Notice"

  }

  triggers {
    gitlab(
      triggerOnPush: true,
      triggerOnMergeRequest: false
    )
  }

  stages {

    stage('Setup : Préparation du workspace') {
      steps {
        script {
          sh '''
          mkdir -p ${WORKSPACE}/artifacts
          '''
        }
      }
    }

    stage('Build Details') {
      steps {
        script {
          sh 'echo "GitLab Source Branch: ${gitlabSourceBranch}"'
          sh 'echo "GitLab Action Type: ${gitlabActionType}"'
          sh 'echo "Calculated Tag Name: ${tagName}"'
        }
      }
    }

    stage('Sécurité : Exécution du scan checkmarx') {
      environment {
        cxProjectGroups = "SSL"
        cxBranch = "${env.BRANCH_NAME}"
        cxProjectTags = "${cxProjectGroups},${env.PROJECT_KEY},${env.APP_KEY}"
        cxScanTag = "${cxProjectGroups},${env.PROJECT_KEY},${env.APP_KEY}"
        cxFileSource = "${env.WORKSPACE}"
        cxOutputName = "chk_${APP_FULL_SAFE_KEY}"
        cxOutputArtifact = "artifacts/${APP_FULL_SAFE_KEY}"
        cxOutputPath = "${env.WORKSPACE}/${cxOutputArtifact}"
        // La clé checkmarx_one_api_key est disponible globalement
        // sur le Jenkins SSL
        cx_apikey = credentials('checkmarx_one_api_key')

        cxArrayAllArgs = [
          "--project-groups ${env.cxProjectGroups}",
          "--project-tags ${env.cxProjectTags}",
          "--project-name ${env.APP_FULL_SAFE_KEY}",
          "--tags ${env.cxScanTag}",
          "--application-name ${env.PROJECT_KEY}",
          "--branch ${env.cxBranch}",
          "--file-source ${env.cxFileSource}",
          "--output-name ${env.cxOutputName}",
          "--output-path ${env.cxOutputPath}",
          '--apikey $cx_apikey'
        ].join(' ').trim()

        cxSonarReportsArgs = [
          "--output-name ${env.cxOutputName}",
          "--output-path ${env.cxOutputPath}",
          "--report-format sonar",
          '--apikey $cx_apikey'
        ].join(' ').trim()

        cxPDFReportsArgs = [
          "--output-name ${env.cxOutputName}",
          "--output-path ${env.cxOutputPath}",
          "--report-format PDF",
          '--apikey $cx_apikey'
        ].join(' ').trim()
      }

      agent {
        docker {
          image 'checkmarx/ast-cli:2.3.17'
          args '--entrypoint='
          reuseNode true
        }
      }

      when {
        allOf {
          anyOf {
            branch 'master'
            branch 'main'
            branch 'develop'
            expression {return env.BRANCH_NAME =~ /^release.*/}
          }
          expression { return env.CHANGE_ID == null;}
        }
      }

      steps {
        script {
          reschk = sh(script: "/app/bin/cx scan create $cxArrayAllArgs", returnStdout: true).trim()
          echo "Résultat du scan : \n${reschk}"
          scanId = sh(script: "echo '${reschk}' | grep -m 1 -o 'Scan ID *: [^ ]*' | awk -F': ' '{print \$2}'", returnStdout: true).trim()
          // On génère le rapport sonar avec le scanId
          sh(script: "/app/bin/cx results show --scan-id ${scanId} $cxSonarReportsArgs", returnStatus: true)
          // On génère le rapport PDF
          sh(script: "/app/bin/cx results show --scan-id ${scanId} $cxPDFReportsArgs", returnStatus: true)
        }
        archiveArtifacts artifacts: "${cxOutputArtifact}/${cxOutputName}.pdf", allowEmptyArchive: true
      }
    }

    stage('SonarQube Analysis') {
      when {
        environment name: 'gitlabSourceBranch', value: 'develop'
        beforeAgent true
      }
      agent {
        docker {
          image 'sonarsource/sonar-scanner-cli:4.6'
          args '-v $PWD:/usr/src'
          reuseNode true
        }
      }
      steps {
        withSonarQubeEnv('SonarQube SSL') {
          script {
            // Execution de l'analyse sonar
            sh 'sonar-scanner -Dsonar.projectKey=$SONARPROJECTKEY -Dsonar.projectName="$SONARPROJECTNAME" -Dsonar.projectVersion=$BUILD_NUMBER -Dproject.settings=sonar-project.properties -Dsonar.branch.name="${gitlabSourceBranch}"'
          }
        }
      }
    }

    stage('Docker build image backoffice') {
      when {
        environment name: 'gitlabActionType', value: 'TAG_PUSH'
        beforeAgent true
      }
      steps {
        script {
          // Construction de l'image docker
          sh 'docker build -t lunt-backoffice:latest -t lunt-backoffice:$tagName lunt-backoffice'

          // Tag de l'image docker pour le nexus
          sh 'docker tag lunt-backoffice:latest sslv-nexus.coexya.eu/lunt-backoffice:latest'
          sh 'docker tag lunt-backoffice:$tagName sslv-nexus.coexya.eu/lunt-backoffice:$tagName'

          // Upload de l'image docker sur le nexus
          sh 'docker push sslv-nexus.coexya.eu/lunt-backoffice:latest'
          sh 'docker push sslv-nexus.coexya.eu/lunt-backoffice:$tagName'

          // Suppression des images docker locales
          sh 'docker rmi -f lunt-backoffice:latest'
          sh 'docker rmi -f sslv-nexus.coexya.eu/lunt-backoffice:latest'
          sh 'docker rmi -f lunt-backoffice:$tagName'
          sh 'docker rmi -f sslv-nexus.coexya.eu/lunt-backoffice:$tagName'
        }
      }
    }

    stage('Docker build image joai') {
      when {
        environment name: 'gitlabActionType', value: 'TAG_PUSH'
        beforeAgent true
      }
      steps {
        script {
          // Construction de l'image docker
          sh 'docker build -t lunt-joai:latest -t lunt-joai:$tagName lunt-joai'

          // Tag de l'image docker pour le nexus
          sh 'docker tag lunt-joai:latest sslv-nexus.coexya.eu/lunt-joai:latest'
          sh 'docker tag lunt-joai:$tagName sslv-nexus.coexya.eu/lunt-joai:$tagName'

          // Upload de l'image docker sur le nexus
          sh 'docker push sslv-nexus.coexya.eu/lunt-joai:latest'
          sh 'docker push sslv-nexus.coexya.eu/lunt-joai:$tagName'

          // Suppression des images docker locales
          sh 'docker rmi -f lunt-joai:latest'
          sh 'docker rmi -f sslv-nexus.coexya.eu/lunt-joai:latest'
          sh 'docker rmi -f lunt-joai:$tagName'
          sh 'docker rmi -f sslv-nexus.coexya.eu/lunt-joai:$tagName'
        }
      }
    }

    stage('Update development platform') {
      agent {
        docker {
          image 'ictu/sshpass'
          reuseNode true
        }
      }
      when {
        environment name: 'gitlabActionType', value: 'TAG_PUSH'
        beforeAgent true
      }
      steps {
        withCredentials([string(credentialsId: 'jenkins-lunt-ssh-development-user-pwd', variable: 'VAR')]) {
          script {
            // Mise à jour de l'environnement avec la nouvelle version
            sh 'sshpass -p ${VAR} ssh -oStrictHostKeyChecking=no user@sslv-lunt-develop.lyon-dev2.local VERSION=${tagName} docker-compose -f /home/user/lunt-indexation-notice/compose.yml up -d'

            // Réinitialisation des données
            sh 'sshpass -p ${VAR} ssh -oStrictHostKeyChecking=no user@sslv-lunt-develop.lyon-dev2.local sh /home/user/lunt-indexation-notice/reset-backoffice-db.sh'
          }
        }
      }
    }
  }
}

def tagName() {
    return "${gitlabSourceBranch}" ? "${gitlabSourceBranch}".split('/')[-1] : 'snapshot'
}
